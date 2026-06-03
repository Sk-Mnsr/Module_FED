<?php

namespace App\Http\Controllers\Monetique;

use App\Http\Controllers\Controller;
use App\Models\CoficarteCard;
use App\Models\CoficarteRecharge;
use App\Models\CoficarteSale;
use App\Models\User;
use App\Support\CoficarteAgenceAccess;
use App\Support\CoficarteMovementLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ChefAgenceController extends Controller
{
    private function ensureChefAgence(?User $user): User
    {
        if (! $user || ! $user->hasRole('ca')) {
            abort(403);
        }

        if ($user->agence_id === null) {
            abort(403, 'Compte non rattaché à une agence.');
        }

        return $user;
    }

    private function ensureChargeClientele(?User $user): User
    {
        if (! $user || ! $user->hasRole('cc')) {
            abort(403);
        }

        if ($user->agence_id === null) {
            abort(403, 'Compte non rattaché à une agence.');
        }

        return $user;
    }

    /**
     * Chargé de clientèle : délester — remettre au pool agence les cartes qui lui sont attribuées.
     */
    public function delesterCcVersChefAgence(Request $request)
    {
        $user = $this->ensureChargeClientele($request->user());

        $cartes = CoficarteCard::query()
            ->where('status', CoficarteCard::STATUS_EN_STOCK)
            ->where('agence_id', $user->agence_id)
            ->where('assigned_to_user_id', $user->id)
            ->orderBy('numero_carte')
            ->get(['id', 'numero_carte', 'reference_facture'])
            ->map(fn (CoficarteCard $c) => [
                'id' => $c->id,
                'numero_carte' => $c->numero_carte,
                'reference_facture' => $c->reference_facture,
            ]);

        return Inertia::render('monetique/Cc/DelesterVersChef', [
            'cartes' => $cartes,
        ]);
    }

    public function delesterCcVersChefAgenceStore(Request $request)
    {
        $user = $this->ensureChargeClientele($request->user());

        $validated = $request->validate([
            'coficarte_card_ids' => 'required|array|min:1',
            'coficarte_card_ids.*' => 'integer|exists:coficarte_cards,id',
        ]);

        DB::transaction(function () use ($validated, $user) {
            $cards = CoficarteCard::query()
                ->whereIn('id', $validated['coficarte_card_ids'])
                ->lockForUpdate()
                ->get();

            foreach ($cards as $card) {
                if ((int) $card->agence_id !== (int) $user->agence_id) {
                    throw ValidationException::withMessages([
                        'coficarte_card_ids' => ['Une ou plusieurs cartes ne sont pas dans votre agence.'],
                    ]);
                }
                if ($card->status !== CoficarteCard::STATUS_EN_STOCK) {
                    throw ValidationException::withMessages([
                        'coficarte_card_ids' => ['Seules les cartes en stock peuvent être délestées.'],
                    ]);
                }
                if ((int) $card->assigned_to_user_id !== (int) $user->id) {
                    throw ValidationException::withMessages([
                        'coficarte_card_ids' => ['Vous ne pouvez délester que les cartes qui vous sont attribuées.'],
                    ]);
                }
            }

            $labelPool = CoficarteAgenceAccess::possesseurLabel($user->agence_id);
            foreach ($cards as $card) {
                $card->update([
                    'assigned_to_user_id' => null,
                    'possesseur' => $labelPool,
                ]);
                CoficarteMovementLogger::log($card, 'delester_vers_chef_agence', [
                    'agence_id' => $user->agence_id,
                ], $user->id);
            }
        });

        return redirect()
            ->route('monetique.cc.delester-chef-agence')
            ->with('success', 'Délestage enregistré : cartes remises au stock agence (chef d’agence).');
    }

    public function retourCartes(Request $request)
    {
        $user = $this->ensureChefAgence($request->user());

        $cartes = CoficarteCard::query()
            ->where('status', CoficarteCard::STATUS_EN_STOCK)
            ->where('agence_id', $user->agence_id)
            ->orderBy('numero_carte')
            ->get([
                'id',
                'numero_carte',
                'reference_facture',
                'assigned_to_user_id',
                'possesseur',
            ])
            ->map(fn (CoficarteCard $c) => [
                'id' => $c->id,
                'numero_carte' => $c->numero_carte,
                'reference_facture' => $c->reference_facture,
                'en_poche_cc' => $c->assigned_to_user_id !== null,
            ]);

        return Inertia::render('monetique/Agence/RetourCartes', [
            'cartes' => $cartes,
        ]);
    }

    public function retourCartesStore(Request $request)
    {
        $user = $this->ensureChefAgence($request->user());

        $validated = $request->validate([
            'coficarte_card_ids' => 'required|array|min:1',
            'coficarte_card_ids.*' => 'integer|exists:coficarte_cards,id',
        ]);

        DB::transaction(function () use ($validated, $user) {
            $ids = $validated['coficarte_card_ids'];
            $q = CoficarteCard::query()->whereIn('id', $ids)->lockForUpdate();
            $cards = $q->get();

            foreach ($cards as $card) {
                if ((int) $card->agence_id !== (int) $user->agence_id) {
                    throw ValidationException::withMessages([
                        'coficarte_card_ids' => ['Une ou plusieurs cartes ne sont pas dans votre agence.'],
                    ]);
                }
                if ($card->status !== CoficarteCard::STATUS_EN_STOCK) {
                    throw ValidationException::withMessages([
                        'coficarte_card_ids' => ['Seules les cartes encore en stock peuvent être retournées.'],
                    ]);
                }
            }

            $labelSiege = CoficarteAgenceAccess::possesseurLabel(null);
            foreach ($cards as $card) {
                $card->update([
                    'agence_id' => null,
                    'assigned_to_user_id' => null,
                    'possesseur' => $labelSiege,
                ]);
                CoficarteMovementLogger::log($card, 'retour_siege', [
                    'agence_origine_id' => $user->agence_id,
                ], $user->id);
            }
        });

        return redirect()
            ->route('monetique.agence.retour-cartes')
            ->with('success', 'Cartes retournées au siège (stock central monétique).');
    }

    public function approvisionnementCc(Request $request)
    {
        $user = $this->ensureChefAgence($request->user());

        $cibles = User::query()
            ->where('agence_id', $user->agence_id)
            ->whereHas('roles', fn ($q) => $q->where('slug', 'cc'))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $cartes = CoficarteCard::query()
            ->where('status', CoficarteCard::STATUS_EN_STOCK)
            ->where('agence_id', $user->agence_id)
            ->whereNull('assigned_to_user_id')
            ->orderBy('numero_carte')
            ->get(['id', 'numero_carte', 'reference_facture', 'prix_vente']);

        return Inertia::render('monetique/Agence/ApprovisionnementCc', [
            'chargeClientele' => $cibles,
            'cartes' => $cartes,
        ]);
    }

    public function approvisionnementCcStore(Request $request)
    {
        $user = $this->ensureChefAgence($request->user());

        $validated = $request->validate([
            'assign_to_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(function ($q) use ($user) {
                    $q->where('agence_id', $user->agence_id)
                        ->whereExists(function ($sub) {
                            $sub->select(DB::raw(1))
                                ->from('user_role')
                                ->join('roles', 'roles.id', '=', 'user_role.role_id')
                                ->whereColumn('user_role.user_id', 'users.id')
                                ->where('roles.slug', 'cc');
                        });
                }),
            ],
            'coficarte_card_ids' => 'required|array|min:1',
            'coficarte_card_ids.*' => 'integer|exists:coficarte_cards,id',
        ]);

        DB::transaction(function () use ($validated, $user) {
            $cards = CoficarteCard::query()
                ->whereIn('id', $validated['coficarte_card_ids'])
                ->lockForUpdate()
                ->get();

            foreach ($cards as $card) {
                if ((int) $card->agence_id !== (int) $user->agence_id) {
                    throw ValidationException::withMessages([
                        'coficarte_card_ids' => ['Périmètre agence invalide.'],
                    ]);
                }
                if ($card->status !== CoficarteCard::STATUS_EN_STOCK) {
                    throw ValidationException::withMessages([
                        'coficarte_card_ids' => ['Carte non disponible.'],
                    ]);
                }
                if ($card->assigned_to_user_id !== null) {
                    throw ValidationException::withMessages([
                        'coficarte_card_ids' => ['Une carte est déjà attribuée à un chargé de clientèle.'],
                    ]);
                }
            }

            foreach ($cards as $card) {
                $card->update(['assigned_to_user_id' => $validated['assign_to_user_id']]);
                CoficarteMovementLogger::log($card, 'assignation_cc', [
                    'assigned_to_user_id' => $validated['assign_to_user_id'],
                ], $user->id);
            }
        });

        return redirect()
            ->route('monetique.agence.approvisionnement-cc')
            ->with('success', 'Approvisionnement enregistré.');
    }

    public function suivi(Request $request)
    {
        $user = $this->ensureChefAgence($request->user());
        $user->load('agence:id,nom,code');

        $enStockPool = CoficarteCard::query()
            ->where('agence_id', $user->agence_id)
            ->where('status', CoficarteCard::STATUS_EN_STOCK)
            ->whereNull('assigned_to_user_id')
            ->count();

        $enStockCc = CoficarteCard::query()
            ->where('agence_id', $user->agence_id)
            ->where('status', CoficarteCard::STATUS_EN_STOCK)
            ->whereNotNull('assigned_to_user_id')
            ->count();

        $ventesMois = CoficarteSale::query()
            ->where('payment_status', CoficarteSale::PAYMENT_ENCAISSE)
            ->whereHas('card', fn ($q) => $q->where('agence_id', $user->agence_id))
            ->whereBetween('date_vente', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->count();

        $ventesTotal = CoficarteSale::query()
            ->where('payment_status', CoficarteSale::PAYMENT_ENCAISSE)
            ->whereHas('card', fn ($q) => $q->where('agence_id', $user->agence_id))
            ->count();

        $rechargesMois = CoficarteRecharge::query()
            ->where('payment_status', CoficarteRecharge::PAYMENT_ENCAISSE)
            ->where('agence_enregistrement_id', $user->agence_id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        $montantRechargesMois = CoficarteRecharge::query()
            ->where('payment_status', CoficarteRecharge::PAYMENT_ENCAISSE)
            ->where('agence_enregistrement_id', $user->agence_id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('montant');

        return Inertia::render('monetique/Agence/Suivi', [
            'agence' => [
                'nom' => $user->agence?->nom ?? '—',
                'code' => $user->agence?->code ?? '',
            ],
            'stats' => [
                'en_stock_pool' => $enStockPool,
                'en_stock_cc' => $enStockCc,
                'ventes_mois' => $ventesMois,
                'ventes_total' => $ventesTotal,
                'recharges_mois' => $rechargesMois,
                'montant_recharges_mois' => (int) $montantRechargesMois,
            ],
            'recharges' => [
                'disponible' => true,
                'message' => 'Recharges encaissées du mois : '.$rechargesMois.' — montant total : '.number_format((float) $montantRechargesMois, 0, ',', ' ').' FCFA.',
            ],
        ]);
    }
}
