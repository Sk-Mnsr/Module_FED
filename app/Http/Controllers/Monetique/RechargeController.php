<?php

namespace App\Http\Controllers\Monetique;

use App\Http\Controllers\Controller;
use App\Models\CoficarteCampaign;
use App\Models\CoficarteCard;
use App\Models\CoficarteRecharge;
use App\Models\User;
use App\Support\CoficarteAgenceAccess;
use App\Support\CoficarteCardNumberGenerator;
use App\Support\CoficarteEncaissementCode;
use App\Support\CoficarteEncaissementRows;
use App\Support\CoficarteMovementLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class RechargeController extends Controller
{
    public function create(Request $request)
    {
        $user = $request->user();
        if (! CoficarteAgenceAccess::canInitiateCoficarteRecharge($user)) {
            abort(403);
        }

        $campagnes = CoficarteCampaign::query()
            ->activeForDate()
            ->when($user->agence_id, fn ($q) => $q->where(function ($w) use ($user) {
                $w->whereNull('agence_id')->orWhere('agence_id', $user->agence_id);
            }))
            ->orderBy('nom')
            ->get(['id', 'nom']);

        return Inertia::render('monetique/Recharges/Nouveau', [
            'campagnes' => $campagnes,
        ]);
    }

    public function store(Request $request)
    {
        if (! CoficarteAgenceAccess::canInitiateCoficarteRecharge($request->user())) {
            abort(403);
        }

        $validated = $request->validate([
            'numero_carte' => ['required', 'string', 'max:64'],
            'montant' => 'required|integer|min:1',
            'titulaire_carte' => 'required|string|max:255',
            'email_titulaire' => 'nullable|email|max:191',
            'honoraire_chargement' => 'required|integer|min:0',
            'commentaire' => 'nullable|string|max:2000',
            'coficarte_campaign_id' => 'nullable|exists:coficarte_campaigns,id',
        ]);

        $norm = CoficarteCardNumberGenerator::normalize($validated['numero_carte']);
        if ($norm === '') {
            throw ValidationException::withMessages([
                'numero_carte' => ['Saisissez le numéro de la carte.'],
            ]);
        }

        $recharge = DB::transaction(function () use ($validated, $request, $norm) {
            $optionalCard = self::findOptionalVenduCardForRecharge($validated['numero_carte'], $request->user());
            $card = null;

            if ($optionalCard !== null) {
                $locked = CoficarteCard::query()->whereKey($optionalCard->id)->lockForUpdate()->first();
                if ($locked && $locked->status === CoficarteCard::STATUS_VENDU) {
                    $card = $locked;
                }
            }

            $agenceEnregistrementId = $card?->agence_id ?? $request->user()->agence_id;

            $recharge = CoficarteRecharge::create([
                'coficarte_card_id' => $card?->id,
                'user_id' => $request->user()->id,
                'montant' => $validated['montant'],
                'numero_carte_saisi' => $norm,
                'agence_enregistrement_id' => $agenceEnregistrementId,
                'titulaire_carte' => trim((string) $validated['titulaire_carte']),
                'email_titulaire' => isset($validated['email_titulaire']) && $validated['email_titulaire'] !== ''
                    ? trim((string) $validated['email_titulaire'])
                    : null,
                'honoraire_chargement' => (int) $validated['honoraire_chargement'],
                'payment_status' => CoficarteRecharge::PAYMENT_EN_ATTENTE,
                'encaissement_code' => CoficarteEncaissementCode::generateForRecharge(),
                'commentaire' => $validated['commentaire'] ?? null,
                'coficarte_campaign_id' => $validated['coficarte_campaign_id'] ?? null,
            ]);

            if ($card !== null) {
                CoficarteMovementLogger::log($card, 'recharge_initiee', [
                    'recharge_id' => $recharge->id,
                    'montant' => $recharge->montant,
                    'honoraire_chargement' => $recharge->honoraire_chargement,
                ], $request->user()->id);
            }

            return $recharge;
        });

        $recharge->load(['user:id,name', 'card:id,numero_carte,agence_id', 'card.agence:id,nom', 'agenceEnregistrement:id,nom']);

        return redirect()
            ->route('monetique.recharges.historique')
            ->with('success', 'Recharge enregistrée — en attente d’encaissement à la caisse.')
            ->with('bordereau_cc', [
                'kind' => 'recharge',
                'row' => CoficarteEncaissementRows::recharge($recharge),
            ]);
    }

    public function historique(Request $request)
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'statut' => 'nullable|string|max:20',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $q = CoficarteRecharge::query()
            ->with(['user:id,name', 'card:id,numero_carte,agence_id', 'card.agence:id,nom', 'agenceEnregistrement:id,nom', 'confirmedBy:id,name', 'campaign:id,nom']);

        CoficarteAgenceAccess::applyRechargeListingScope($q, $request->user());

        $statut = isset($validated['statut']) ? trim((string) $validated['statut']) : '';
        if (in_array($statut, [CoficarteRecharge::PAYMENT_EN_ATTENTE, CoficarteRecharge::PAYMENT_ENCAISSE], true)) {
            $q->where('payment_status', $statut);
        }

        if (! empty($validated['q'])) {
            $term = trim((string) $validated['q']);
            $digits = preg_replace('/\D+/', '', $term);
            $q->where(function ($w) use ($term, $digits) {
                $w->whereHas('user', fn ($u) => $u->where('name', 'like', '%'.$term.'%'))
                    ->orWhereHas('confirmedBy', fn ($u) => $u->where('name', 'like', '%'.$term.'%'))
                    ->orWhere('numero_carte_saisi', 'like', '%'.$term.'%')
                    ->orWhere('titulaire_carte', 'like', '%'.$term.'%')
                    ->orWhereHas('card', function ($c) use ($term, $digits) {
                        if ($digits !== '') {
                            $c->where('numero_carte', 'like', '%'.$digits.'%');
                        } else {
                            $c->where('numero_carte', 'like', '%'.$term.'%');
                        }
                    });
                if ($digits !== '') {
                    $w->orWhere('numero_carte_saisi', 'like', '%'.$digits.'%');
                }
            });
        }

        $perPage = (int) (($validated['per_page'] ?? null) ?: 15);

        $recharges = $q->latest()->paginate($perPage)->withQueryString()->through(function (CoficarteRecharge $r) {
            $paiement = match ($r->payment_status) {
                CoficarteRecharge::PAYMENT_EN_ATTENTE => 'En attente caisse',
                CoficarteRecharge::PAYMENT_ENCAISSE => 'Encaissé',
                default => $r->payment_status,
            };

            $numeroAffiche = $r->card?->numero_carte ?? $r->numero_carte_saisi ?? '—';

            return [
                'id' => $r->id,
                'numero_carte' => $numeroAffiche,
                'carte_interne' => $r->coficarte_card_id !== null,
                'montant' => $r->montant,
                'titulaire_carte' => $r->titulaire_carte,
                'email_titulaire' => $r->email_titulaire,
                'honoraire_chargement' => (int) ($r->honoraire_chargement ?? 0),
                'montant_total_affichage' => (int) $r->montant + (int) ($r->honoraire_chargement ?? 0),
                'payment_status' => $r->payment_status,
                'paiement' => $paiement,
                'demandeur' => $r->user?->name ?? '—',
                'caissier' => $r->confirmedBy?->name ?? '—',
                'campagne' => $r->campaign?->nom,
                'created_at' => $r->created_at?->format('d/m/Y H:i'),
                'confirmed_at' => $r->confirmed_at?->format('d/m/Y H:i'),
                'encaissement_code' => $r->encaissement_code,
                'bordereau_caisse_url' => $r->bordereau_caisse_path
                    ? Storage::disk('public')->url($r->bordereau_caisse_path)
                    : null,
                'bordereau_cc_payload' => [
                    'kind' => 'recharge',
                    'row' => CoficarteEncaissementRows::recharge($r),
                ],
            ];
        });

        return Inertia::render('monetique/Recharges/Historique', [
            'recharges' => $recharges,
            'filters' => [
                'q' => $validated['q'] ?? '',
                'statut' => $statut,
            ],
        ]);
    }

    /**
     * Carte vendue dans le périmètre recharge, si une correspondance unique existe ; sinon null (recharge « externe »).
     *
     * @throws ValidationException
     */
    private static function findOptionalVenduCardForRecharge(string $saisi, User $user): ?CoficarteCard
    {
        $norm = CoficarteCardNumberGenerator::normalize($saisi);
        if ($norm === '') {
            throw ValidationException::withMessages([
                'numero_carte' => ['Saisissez le numéro de la carte.'],
            ]);
        }

        $digits = preg_replace('/\D+/', '', $norm) ?? '';

        $query = CoficarteCard::query()->where('status', CoficarteCard::STATUS_VENDU);

        if ($digits !== '' && strlen($digits) >= 6) {
            $query->where(function ($w) use ($norm, $digits) {
                $w->where('numero_carte', $norm)->orWhere('numero_carte', 'like', '%'.$digits.'%');
            });
        } else {
            $query->where('numero_carte', $norm);
        }

        CoficarteAgenceAccess::applyRechargeCardLookupScope($query, $user);

        $candidates = $query->limit(25)->get(['id', 'numero_carte', 'status']);

        $matches = $candidates->filter(function (CoficarteCard $c) use ($norm, $digits) {
            $cNorm = CoficarteCardNumberGenerator::normalize((string) $c->numero_carte);
            if ($cNorm === $norm) {
                return true;
            }
            if ($digits !== '') {
                return preg_replace('/\D+/', '', $cNorm) === $digits;
            }

            return false;
        })->values();

        if ($matches->isEmpty()) {
            return null;
        }

        if ($matches->count() > 1) {
            throw ValidationException::withMessages([
                'numero_carte' => ['Plusieurs cartes correspondent : indiquez le numéro complet tel qu’il figure sur la carte.'],
            ]);
        }

        /** @var CoficarteCard $one */
        $one = $matches->first();

        return $one;
    }
}
