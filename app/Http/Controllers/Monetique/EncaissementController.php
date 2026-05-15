<?php

namespace App\Http\Controllers\Monetique;

use App\Http\Controllers\Controller;
use App\Models\CoficarteCard;
use App\Models\CoficarteRecharge;
use App\Models\CoficarteSale;
use App\Support\CoficarteAgenceAccess;
use App\Support\CoficarteEncaissementCode;
use App\Support\CoficarteEncaissementRows;
use App\Support\CoficarteMovementLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class EncaissementController extends Controller
{
    private function ensureCaissierOuMonetique(Request $request): void
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }
        if (! $user->hasRole('caissier') && ! CoficarteAgenceAccess::canViewAll($user)) {
            abort(403);
        }
    }

    /**
     * Page unique caisse : saisie du code (vente ou recharge) + onglet historique.
     */
    public function caisse(Request $request)
    {
        $this->ensureCaissierOuMonetique($request);
        $user = $request->user();

        $validated = $request->validate([
            'code' => 'nullable|string|max:64',
            'onglet' => 'nullable|string|in:encaissement,historique',
        ]);

        $onglet = $validated['onglet'] ?? 'encaissement';
        if (! in_array($onglet, ['encaissement', 'historique'], true)) {
            $onglet = 'encaissement';
        }

        $codeNormalized = CoficarteEncaissementCode::normalize($validated['code'] ?? null);

        $operationParCode = null;
        $codeIntrouvable = false;

        if ($codeNormalized !== '') {
            $saleParCode = $this->findVenteEnAttenteParCode($codeNormalized, $user);
            if ($saleParCode !== null) {
                $operationParCode = [
                    'kind' => 'vente',
                    'row' => CoficarteEncaissementRows::sale($saleParCode),
                ];
            } else {
                $rechargeParCode = $this->findRechargeEnAttenteParCode($codeNormalized, $user);
                if ($rechargeParCode !== null) {
                    $operationParCode = [
                        'kind' => 'recharge',
                        'row' => CoficarteEncaissementRows::recharge($rechargeParCode),
                    ];
                } else {
                    $codeIntrouvable = true;
                }
            }
        }

        $historiqueVentes = $this->historiqueVentesEncaissees($user);
        $historiqueRecharges = $this->historiqueRechargesEncaissees($user);

        return Inertia::render('monetique/Encaissements/Caisse', [
            'onglet' => $onglet,
            'code_recherche' => $validated['code'] ?? '',
            'code_introuvable' => $codeIntrouvable,
            'operation_par_code' => $operationParCode,
            'historique_ventes' => $historiqueVentes,
            'historique_recharges' => $historiqueRecharges,
        ]);
    }

    private function findVenteEnAttenteParCode(string $code, $user): ?CoficarteSale
    {
        $saleQuery = CoficarteSale::query()
            ->where('payment_status', CoficarteSale::PAYMENT_EN_ATTENTE)
            ->where('encaissement_code', $code);

        if (! CoficarteAgenceAccess::canViewAll($user)) {
            if ($user->agence_id === null) {
                abort(403);
            }
            $saleQuery->whereHas('card', fn ($c) => $c->where('agence_id', $user->agence_id));
        }

        return $saleQuery->first();
    }

    private function findRechargeEnAttenteParCode(string $code, $user): ?CoficarteRecharge
    {
        $rechargeQuery = CoficarteRecharge::query()
            ->where('payment_status', CoficarteRecharge::PAYMENT_EN_ATTENTE)
            ->where('encaissement_code', $code);

        if (! CoficarteAgenceAccess::canViewAll($user)) {
            if ($user->agence_id === null) {
                abort(403);
            }
            $aid = (int) $user->agence_id;
            $rechargeQuery->where(function ($w) use ($aid) {
                $w->where('agence_enregistrement_id', $aid)
                    ->orWhereHas('card', fn ($c) => $c->where('agence_id', $aid));
            });
        }

        return $rechargeQuery->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function historiqueVentesEncaissees($user): array
    {
        $q = CoficarteSale::query()
            ->where('payment_status', CoficarteSale::PAYMENT_ENCAISSE)
            ->with(['user:id,name', 'card:id,numero_carte,agence_id,prix_vente', 'card.agence:id,nom']);

        if (! CoficarteAgenceAccess::canViewAll($user)) {
            if ($user->agence_id === null) {
                abort(403);
            }
            $q->whereHas('card', fn ($c) => $c->where('agence_id', $user->agence_id));
        }

        return $q->orderByDesc('activated_at')
            ->limit(50)
            ->get()
            ->map(fn (CoficarteSale $s) => [
                'encaissement_code' => $s->encaissement_code,
                'numero_carte' => $s->card?->numero_carte ?? '—',
                'montant' => (int) ($s->card?->prix_vente ?? 0) + (int) ($s->montant_premiere_recharge ?? 0),
                'libelle' => $s->nom_client ?? '—',
                'agence' => $s->card?->agence?->nom ?? '—',
                'date' => $s->activated_at?->format('d/m/Y H:i') ?? '—',
                'bordereau_caisse_url' => $s->bordereau_caisse_path
                    ? Storage::disk('public')->url($s->bordereau_caisse_path)
                    : null,
                'bordereau_cc_payload' => [
                    'kind' => 'vente',
                    'row' => CoficarteEncaissementRows::sale($s),
                ],
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function historiqueRechargesEncaissees($user): array
    {
        $q = CoficarteRecharge::query()
            ->where('payment_status', CoficarteRecharge::PAYMENT_ENCAISSE)
            ->with(['user:id,name', 'card:id,numero_carte,agence_id', 'card.agence:id,nom', 'agenceEnregistrement:id,nom']);

        if (! CoficarteAgenceAccess::canViewAll($user)) {
            if ($user->agence_id === null) {
                abort(403);
            }
            $aid = (int) $user->agence_id;
            $q->where(function ($w) use ($aid) {
                $w->where('agence_enregistrement_id', $aid)
                    ->orWhereHas('card', fn ($c) => $c->where('agence_id', $aid));
            });
        }

        return $q->orderByDesc('confirmed_at')
            ->limit(50)
            ->get()
            ->map(fn (CoficarteRecharge $r) => [
                'encaissement_code' => $r->encaissement_code,
                'numero_carte' => $r->card?->numero_carte ?? $r->numero_carte_saisi ?? '—',
                'montant' => (int) $r->montant + (int) ($r->honoraire_chargement ?? 0),
                'libelle' => $r->user?->name ?? '—',
                'agence' => $r->card?->agence?->nom ?? $r->agenceEnregistrement?->nom ?? '—',
                'date' => $r->confirmed_at?->format('d/m/Y H:i') ?? '—',
                'bordereau_caisse_url' => $r->bordereau_caisse_path
                    ? Storage::disk('public')->url($r->bordereau_caisse_path)
                    : null,
                'bordereau_cc_payload' => [
                    'kind' => 'recharge',
                    'row' => CoficarteEncaissementRows::recharge($r),
                ],
            ])
            ->all();
    }

    public function confirmerVente(Request $request, CoficarteSale $coficarteSale)
    {
        $this->ensureCaissierOuMonetique($request);
        $user = $request->user();

        $request->validate([
            'bordereau_caisse' => 'required|file|mimes:pdf,jpeg,jpg,png|max:10240',
        ], [
            'bordereau_caisse.required' => 'Le bordereau caisse est obligatoire pour valider l’encaissement.',
        ]);

        if ($coficarteSale->payment_status !== CoficarteSale::PAYMENT_EN_ATTENTE) {
            return redirect()->route('monetique.encaissements')->with('error', 'Cette vente n’est pas en attente d’encaissement.');
        }

        $path = $request->file('bordereau_caisse')->store('coficarte/bordereaux-caisse', 'public');

        DB::transaction(function () use ($coficarteSale, $user, $path) {
            $sale = CoficarteSale::query()->whereKey($coficarteSale->id)->lockForUpdate()->firstOrFail();
            if ($sale->payment_status !== CoficarteSale::PAYMENT_EN_ATTENTE) {
                throw ValidationException::withMessages([
                    'vente' => ['Statut déjà modifié.'],
                ]);
            }

            $cardQuery = CoficarteCard::query()->whereKey($sale->coficarte_card_id)->lockForUpdate();
            $card = $cardQuery->firstOrFail();

            if (! CoficarteAgenceAccess::canViewAll($user)) {
                if ((int) $card->agence_id !== (int) $user->agence_id) {
                    abort(403);
                }
            }

            if ($card->status !== CoficarteCard::STATUS_EN_ATTENTE_ENCAISSEMENT) {
                throw ValidationException::withMessages([
                    'vente' => ['Carte dans un état inattendu.'],
                ]);
            }

            $sale->update([
                'payment_status' => CoficarteSale::PAYMENT_ENCAISSE,
                'activated_at' => now(),
                'bordereau_caisse_path' => $path,
            ]);

            $card->update(['status' => CoficarteCard::STATUS_VENDU]);

            CoficarteMovementLogger::log($card, 'vente_encaissee', [
                'sale_id' => $sale->id,
            ], $user->id);
        });

        return redirect()->route('monetique.encaissements', ['onglet' => 'encaissement'])->with('success', 'Encaissement confirmé — carte activée (vendue).');
    }

    public function confirmerRecharge(Request $request, CoficarteRecharge $coficarteRecharge)
    {
        $this->ensureCaissierOuMonetique($request);
        $user = $request->user();

        $request->validate([
            'bordereau_caisse' => 'required|file|mimes:pdf,jpeg,jpg,png|max:10240',
        ], [
            'bordereau_caisse.required' => 'Le bordereau caisse est obligatoire pour valider l’encaissement.',
        ]);

        if ($coficarteRecharge->payment_status !== CoficarteRecharge::PAYMENT_EN_ATTENTE) {
            return redirect()->route('monetique.encaissements')->with('error', 'Cette recharge n’est pas en attente d’encaissement.');
        }

        $path = $request->file('bordereau_caisse')->store('coficarte/bordereaux-caisse', 'public');

        DB::transaction(function () use ($coficarteRecharge, $user, $path) {
            $recharge = CoficarteRecharge::query()->whereKey($coficarteRecharge->id)->lockForUpdate()->firstOrFail();
            if ($recharge->payment_status !== CoficarteRecharge::PAYMENT_EN_ATTENTE) {
                throw ValidationException::withMessages([
                    'recharge' => ['Statut déjà modifié.'],
                ]);
            }

            if ($recharge->coficarte_card_id) {
                $card = CoficarteCard::query()->whereKey($recharge->coficarte_card_id)->lockForUpdate()->firstOrFail();

                if (! CoficarteAgenceAccess::canViewAll($user)) {
                    if ((int) $card->agence_id !== (int) $user->agence_id) {
                        abort(403);
                    }
                }

                $recharge->update([
                    'payment_status' => CoficarteRecharge::PAYMENT_ENCAISSE,
                    'confirmed_by_user_id' => $user->id,
                    'confirmed_at' => now(),
                    'bordereau_caisse_path' => $path,
                ]);

                CoficarteMovementLogger::log($card, 'recharge_encaissee', [
                    'recharge_id' => $recharge->id,
                    'montant' => $recharge->montant,
                ], $user->id);

                return;
            }

            if (! CoficarteAgenceAccess::canViewAll($user)) {
                if ($user->agence_id === null || (int) ($recharge->agence_enregistrement_id ?? 0) !== (int) $user->agence_id) {
                    abort(403);
                }
            }

            $recharge->update([
                'payment_status' => CoficarteRecharge::PAYMENT_ENCAISSE,
                'confirmed_by_user_id' => $user->id,
                'confirmed_at' => now(),
                'bordereau_caisse_path' => $path,
            ]);
        });

        return redirect()->route('monetique.encaissements', ['onglet' => 'encaissement'])->with('success', 'Encaissement recharge confirmé.');
    }
}
