<?php

namespace App\Http\Controllers\Monetique;

use App\Http\Controllers\Controller;
use App\Models\Agence;
use App\Models\CoficarteCampaign;
use App\Models\CoficarteRecharge;
use App\Models\CoficarteSale;
use App\Models\CoficarteStockThreshold;
use App\Support\CoficarteAgenceAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PilotageController extends Controller
{
    public function index(Request $request)
    {
        [$from, $to, $periodeMeta] = $this->resolvePeriode($request);
        $payload = $this->buildPayload($request, $from, $to, $periodeMeta);

        return Inertia::render('monetique/Pilotage/Index', $payload);
    }

    public function export(Request $request): StreamedResponse
    {
        [$from, $to, $periodeMeta] = $this->resolvePeriode($request);
        $payload = $this->buildPayload($request, $from, $to, $periodeMeta);

        $filename = sprintf(
            'pilotage_coficarte_%s_%s.csv',
            $from->format('Ymd'),
            $to->format('Ymd'),
        );

        return response()->streamDownload(function () use ($payload, $periodeMeta) {
            echo "\xEF\xBB\xBF";
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Période',
                $periodeMeta['debut'].' — '.$periodeMeta['fin'],
                'Preset',
                $periodeMeta['preset'],
            ]);
            fputcsv($handle, []);
            fputcsv($handle, [
                'Agence',
                'Nb ventes',
                'Objectif ventes',
                '% ventes',
                'Volume ventes (F CFA)',
                'Montant recharges (F CFA)',
                'Objectif recharges',
                '% recharges',
                'Écart ventes',
            ]);

            foreach ($payload['ventes_par_agence'] as $row) {
                fputcsv($handle, [
                    $row['agence'],
                    $row['nb_ventes'],
                    $row['objectif_nb_ventes'],
                    $row['pct_ventes'] ?? '',
                    $row['volume_ventes'],
                    $row['montant_recharges'],
                    $row['objectif_montant_recharges'],
                    $row['pct_recharges'] ?? '',
                    $row['ecart_ventes'],
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, [
                'TOTAL',
                $payload['totaux']['nb_ventes'],
                $payload['objectifs_reseau']['nb_ventes'],
                '',
                $payload['totaux']['volume_ventes'],
                $payload['totaux']['montant_recharges'],
                $payload['objectifs_reseau']['montant_recharges'],
                '',
                '',
            ]);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: array{debut: string, fin: string, preset: string, debut_iso: string, fin_iso: string}}
     */
    private function resolvePeriode(Request $request): array
    {
        $preset = $request->string('preset', 'current_month')->toString();
        if (! in_array($preset, ['current_month', 'previous_month', 'custom'], true)) {
            $preset = 'current_month';
        }

        if ($preset === 'previous_month') {
            $from = now()->subMonthNoOverflow()->startOfMonth();
            $to = now()->subMonthNoOverflow()->endOfMonth();
        } elseif ($preset === 'custom') {
            $fromInput = $request->input('from');
            $toInput = $request->input('to');
            $from = $fromInput ? Carbon::parse($fromInput)->startOfDay() : now()->startOfMonth();
            $to = $toInput ? Carbon::parse($toInput)->endOfDay() : now()->endOfMonth();
            if ($from->gt($to)) {
                [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
            }
            // Limite de sécurité : 366 jours
            if ($from->diffInDays($to) > 366) {
                $to = $from->copy()->addDays(366)->endOfDay();
            }
        } else {
            $preset = 'current_month';
            $from = now()->startOfMonth();
            $to = now()->endOfMonth();
        }

        return [
            $from,
            $to,
            [
                'debut' => $from->format('d/m/Y'),
                'fin' => $to->format('d/m/Y'),
                'preset' => $preset,
                'debut_iso' => $from->toDateString(),
                'fin_iso' => $to->toDateString(),
            ],
        ];
    }

    private function buildPayload(Request $request, Carbon $from, Carbon $to, array $periodeMeta): array
    {
        $user = $request->user();
        $central = CoficarteAgenceAccess::canViewAll($user);

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
            $nbVentesAgence = (int) $row->nb;
            $objVentes = (int) ($thresh?->objectif_nb_ventes_mois ?? 0);
            $objRecharges = (int) ($thresh?->objectif_montant_recharges_mois ?? 0);

            $pctVentes = $objVentes > 0 ? (int) round(($nbVentesAgence / $objVentes) * 100) : null;
            $pctRecharges = $objRecharges > 0 ? (int) round(($recMontant / $objRecharges) * 100) : null;

            return [
                'agence_id' => $aid,
                'agence' => $row->agence_id ? ($agences[(int) $row->agence_id]->nom ?? 'Agence #'.$row->agence_id) : 'Siège',
                'nb_ventes' => $nbVentesAgence,
                'volume_ventes' => (int) $row->volume,
                'montant_recharges' => $recMontant,
                'objectif_nb_ventes' => $objVentes,
                'objectif_montant_recharges' => $objRecharges,
                'pct_ventes' => $pctVentes,
                'pct_recharges' => $pctRecharges,
                'ecart_ventes' => $objVentes > 0 ? $nbVentesAgence - $objVentes : 0,
            ];
        })
            ->sortBy(function ($row) {
                // Agences avec objectif d'abord, triées par % croissant (retards en tête)
                if ($row['pct_ventes'] === null) {
                    return 9999;
                }

                return $row['pct_ventes'];
            })
            ->values();

        $alertes = $ventesParAgenceFormatted
            ->filter(fn ($row) => $row['objectif_nb_ventes'] > 0 && ($row['pct_ventes'] ?? 100) < 70)
            ->map(fn ($row) => [
                'agence' => $row['agence'],
                'pct_ventes' => $row['pct_ventes'],
                'nb_ventes' => $row['nb_ventes'],
                'objectif_nb_ventes' => $row['objectif_nb_ventes'],
            ])
            ->values();

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

        $campagnesProgress = $campagnes->map(function (CoficarteCampaign $c) use ($from, $to, $central, $user) {
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

        $serieJournaliere = $this->buildSerieJournaliere($from, $to, $central, $user);

        $ticketMoyen = $nbVentes > 0 ? (int) round(((int) $volumeVentes) / $nbVentes) : 0;
        $ratioRechargesVentes = $nbVentes > 0
            ? round($nbRecharges / $nbVentes, 2)
            : null;

        return [
            'periode' => $periodeMeta,
            'totaux' => [
                'nb_ventes' => $nbVentes,
                'volume_ventes' => (int) $volumeVentes,
                'nb_recharges' => $nbRecharges,
                'montant_recharges' => (int) $montantRecharges,
                'ticket_moyen' => $ticketMoyen,
                'ratio_recharges_ventes' => $ratioRechargesVentes,
            ],
            'objectifs_reseau' => [
                'nb_ventes' => (int) ($thresholdCentral?->objectif_nb_ventes_mois ?? 0),
                'montant_recharges' => (int) ($thresholdCentral?->objectif_montant_recharges_mois ?? 0),
            ],
            'ventes_par_agence' => $ventesParAgenceFormatted,
            'ventes_par_cc' => $ventesParCc,
            'ventes_par_apporteur' => $ventesParApporteur,
            'campagnes' => $campagnesProgress,
            'serie_journaliere' => $serieJournaliere,
            'alertes' => $alertes,
            'perimetre' => $central ? 'reseau' : 'agence',
        ];
    }

    /**
     * @return list<array{date: string, label: string, nb_ventes: int, montant_recharges: int}>
     */
    private function buildSerieJournaliere(Carbon $from, Carbon $to, bool $central, $user): array
    {
        $ventesParJour = CoficarteSale::query()
            ->select('coficarte_sales.date_vente', DB::raw('count(*) as nb'))
            ->join('coficarte_cards', 'coficarte_cards.id', '=', 'coficarte_sales.coficarte_card_id')
            ->where('coficarte_sales.payment_status', CoficarteSale::PAYMENT_ENCAISSE)
            ->whereBetween('coficarte_sales.date_vente', [$from->toDateString(), $to->toDateString()])
            ->when(! $central && $user && $user->agence_id, fn ($q) => $q->where('coficarte_cards.agence_id', $user->agence_id))
            ->groupBy('coficarte_sales.date_vente')
            ->pluck('nb', 'date_vente');

        $rechargesParJour = CoficarteRecharge::query()
            ->select(DB::raw('DATE(coficarte_recharges.created_at) as jour'), DB::raw('COALESCE(SUM(montant), 0) as montant'))
            ->where('payment_status', CoficarteRecharge::PAYMENT_ENCAISSE)
            ->whereBetween('coficarte_recharges.created_at', [$from, $to])
            ->when(! $central && $user && $user->agence_id, fn ($q) => $q->where('agence_enregistrement_id', $user->agence_id))
            ->groupBy(DB::raw('DATE(coficarte_recharges.created_at)'))
            ->pluck('montant', 'jour');

        $serie = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $serie[] = [
                'date' => $key,
                'label' => $cursor->format('d/m'),
                'nb_ventes' => (int) ($ventesParJour[$key] ?? 0),
                'montant_recharges' => (int) ($rechargesParJour[$key] ?? 0),
            ];
            $cursor->addDay();
        }

        return $serie;
    }
}
