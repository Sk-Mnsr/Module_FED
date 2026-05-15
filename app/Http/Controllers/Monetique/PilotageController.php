<?php

namespace App\Http\Controllers\Monetique;

use App\Http\Controllers\Controller;
use App\Models\Agence;
use App\Models\CoficarteCampaign;
use App\Models\CoficarteRecharge;
use App\Models\CoficarteSale;
use App\Models\CoficarteStockThreshold;
use App\Support\CoficarteAgenceAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PilotageController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $central = CoficarteAgenceAccess::canViewAll($user);

        $from = now()->startOfMonth();
        $to = now()->endOfMonth();

        $thresholdCentral = CoficarteStockThreshold::query()
            ->where('cible', CoficarteStockThreshold::CIBLE_CENTRAL)
            ->whereNull('agence_id')
            ->first();

        $thresholdsAgence = CoficarteStockThreshold::query()
            ->where('cible', CoficarteStockThreshold::CIBLE_AGENCE)
            ->get()
            ->keyBy('agence_id');

        $rechargesMontantParAgence = CoficarteRecharge::query()
            ->select('agence_enregistrement_id', DB::raw('COALESCE(SUM(montant), 0) as montant'))
            ->where('payment_status', CoficarteRecharge::PAYMENT_ENCAISSE)
            ->whereBetween('coficarte_recharges.created_at', [$from, $to])
            ->whereNotNull('agence_enregistrement_id')
            ->when(! $central && $user && $user->agence_id, fn ($q) => $q->where('agence_enregistrement_id', $user->agence_id))
            ->groupBy('agence_enregistrement_id')
            ->pluck('montant', 'agence_enregistrement_id');

        $salesBase = CoficarteSale::query()
            ->where('payment_status', CoficarteSale::PAYMENT_ENCAISSE)
            ->whereBetween('date_vente', [$from->toDateString(), $to->toDateString()])
            ->whereHas('card', function ($q) use ($central, $user) {
                if (! $central && $user && $user->agence_id) {
                    $q->where('agence_id', $user->agence_id);
                }
            });

        $nbVentes = (clone $salesBase)->count();
        $volumeVentes = (clone $salesBase)->join('coficarte_cards', 'coficarte_cards.id', '=', 'coficarte_sales.coficarte_card_id')
            ->sum('coficarte_cards.prix_vente');

        $rechargesBase = CoficarteRecharge::query()
            ->where('payment_status', CoficarteRecharge::PAYMENT_ENCAISSE)
            ->whereBetween('coficarte_recharges.created_at', [$from, $to])
            ->when(! $central && $user && $user->agence_id, fn ($q) => $q->where('agence_enregistrement_id', $user->agence_id));

        $nbRecharges = (clone $rechargesBase)->count();
        $montantRecharges = (clone $rechargesBase)->sum('coficarte_recharges.montant');

        $ventesParAgence = CoficarteSale::query()
            ->select('coficarte_cards.agence_id', DB::raw('count(*) as nb'), DB::raw('sum(coficarte_cards.prix_vente) as volume'))
            ->join('coficarte_cards', 'coficarte_cards.id', '=', 'coficarte_sales.coficarte_card_id')
            ->where('coficarte_sales.payment_status', CoficarteSale::PAYMENT_ENCAISSE)
            ->whereBetween('coficarte_sales.date_vente', [$from->toDateString(), $to->toDateString()])
            ->when(! $central && $user && $user->agence_id, fn ($q) => $q->where('coficarte_cards.agence_id', $user->agence_id))
            ->groupBy('coficarte_cards.agence_id')
            ->get();

        $agences = Agence::query()->whereIn('id', $ventesParAgence->pluck('agence_id')->filter())->get(['id', 'nom'])->keyBy('id');

        $ventesParAgenceFormatted = $ventesParAgence->map(function ($row) use ($agences, $thresholdsAgence, $rechargesMontantParAgence) {
            $aid = $row->agence_id !== null ? (int) $row->agence_id : null;
            $thresh = $aid !== null ? $thresholdsAgence->get($aid) : null;
            $recMontant = $aid !== null ? (int) ($rechargesMontantParAgence[$aid] ?? 0) : 0;

            return [
                'agence_id' => $aid,
                'agence' => $row->agence_id ? ($agences[(int) $row->agence_id]->nom ?? 'Agence #'.$row->agence_id) : 'Siège',
                'nb_ventes' => (int) $row->nb,
                'volume_ventes' => (int) $row->volume,
                'montant_recharges' => $recMontant,
                'objectif_nb_ventes' => (int) ($thresh?->objectif_nb_ventes_mois ?? 0),
                'objectif_montant_recharges' => (int) ($thresh?->objectif_montant_recharges_mois ?? 0),
            ];
        })->values();

        $ventesParCc = CoficarteSale::query()
            ->join('users', 'users.id', '=', 'coficarte_sales.user_id')
            ->join('coficarte_cards', 'coficarte_cards.id', '=', 'coficarte_sales.coficarte_card_id')
            ->where('coficarte_sales.payment_status', CoficarteSale::PAYMENT_ENCAISSE)
            ->whereBetween('coficarte_sales.date_vente', [$from->toDateString(), $to->toDateString()])
            ->when(! $central && $user && $user->agence_id, fn ($q) => $q->where('coficarte_cards.agence_id', $user->agence_id))
            ->select('users.name', DB::raw('count(*) as nb'))
            ->groupBy('coficarte_sales.user_id', 'users.name')
            ->orderByDesc('nb')
            ->limit(20)
            ->get()
            ->map(fn ($r) => ['nom' => $r->name, 'nb_ventes' => (int) $r->nb]);

        $ventesParApporteur = CoficarteSale::query()
            ->join('coficarte_apporteurs', 'coficarte_apporteurs.id', '=', 'coficarte_sales.coficarte_apporteur_id')
            ->join('coficarte_cards', 'coficarte_cards.id', '=', 'coficarte_sales.coficarte_card_id')
            ->where('coficarte_sales.payment_status', CoficarteSale::PAYMENT_ENCAISSE)
            ->whereBetween('coficarte_sales.date_vente', [$from->toDateString(), $to->toDateString()])
            ->when(! $central && $user && $user->agence_id, fn ($q) => $q->where('coficarte_cards.agence_id', $user->agence_id))
            ->select('coficarte_apporteurs.nom', DB::raw('count(*) as nb'))
            ->groupBy('coficarte_apporteurs.id', 'coficarte_apporteurs.nom')
            ->orderByDesc('nb')
            ->limit(20)
            ->get()
            ->map(fn ($r) => ['apporteur' => $r->nom, 'nb_ventes' => (int) $r->nb]);

        $campagnes = CoficarteCampaign::query()
            ->activeForDate()
            ->when(! $central && $user && $user->agence_id, fn ($q) => $q->where(function ($w) use ($user) {
                $w->whereNull('agence_id')->orWhere('agence_id', $user->agence_id);
            }))
            ->get();

        $campagnesProgress = $campagnes->map(function (CoficarteCampaign $c) use ($from, $to) {
            $ventes = CoficarteSale::query()
                ->where('coficarte_campaign_id', $c->id)
                ->where('payment_status', CoficarteSale::PAYMENT_ENCAISSE)
                ->whereBetween('date_vente', [$from->toDateString(), $to->toDateString()])
                ->count();

            $montantRec = CoficarteRecharge::query()
                ->where('coficarte_campaign_id', $c->id)
                ->where('payment_status', CoficarteRecharge::PAYMENT_ENCAISSE)
                ->whereBetween('created_at', [$from, $to])
                ->when(! $central && $user && $user->agence_id, fn ($q) => $q->where('agence_enregistrement_id', $user->agence_id))
                ->sum('montant');

            return [
                'id' => $c->id,
                'nom' => $c->nom,
                'objectif_ventes' => $c->objectif_ventes,
                'ventes_realisees' => $ventes,
                'objectif_montant_recharges' => $c->objectif_montant_recharges,
                'montant_recharges' => (int) $montantRec,
            ];
        });

        return Inertia::render('monetique/Pilotage/Index', [
            'periode' => [
                'debut' => $from->format('d/m/Y'),
                'fin' => $to->format('d/m/Y'),
            ],
            'totaux' => [
                'nb_ventes' => $nbVentes,
                'volume_ventes' => (int) $volumeVentes,
                'nb_recharges' => $nbRecharges,
                'montant_recharges' => (int) $montantRecharges,
            ],
            'objectifs_reseau' => [
                'nb_ventes' => (int) ($thresholdCentral?->objectif_nb_ventes_mois ?? 0),
                'montant_recharges' => (int) ($thresholdCentral?->objectif_montant_recharges_mois ?? 0),
            ],
            'ventes_par_agence' => $ventesParAgenceFormatted,
            'ventes_par_cc' => $ventesParCc,
            'ventes_par_apporteur' => $ventesParApporteur,
            'campagnes' => $campagnesProgress,
        ]);
    }
}
