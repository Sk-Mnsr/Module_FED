<?php

namespace App\Http\Controllers\Monetique;

use App\Http\Controllers\Controller;
use App\Models\Agence;
use App\Models\CoficarteStockThreshold;
use App\Support\CoficarteAgenceAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StockThresholdController extends Controller
{
    public function edit(Request $request)
    {
        if (! CoficarteAgenceAccess::canViewAll($request->user())) {
            abort(403);
        }

        $central = CoficarteStockThreshold::query()
            ->where('cible', CoficarteStockThreshold::CIBLE_CENTRAL)
            ->whereNull('agence_id')
            ->first();

        $parAgence = CoficarteStockThreshold::query()
            ->where('cible', CoficarteStockThreshold::CIBLE_AGENCE)
            ->with('agence:id,nom,code')
            ->get()
            ->keyBy('agence_id');

        $seuilsAgences = Agence::query()
            ->orderBy('nom')
            ->get(['id', 'nom', 'code'])
            ->map(function (Agence $a) use ($parAgence) {
                $t = $parAgence->get($a->id);

                return [
                    'id' => $t?->id,
                    'agence_id' => $a->id,
                    'agence_nom' => $a->nom,
                    'min_cards' => $t?->min_cards ?? 0,
                    'objectif_nb_ventes_mois' => $t?->objectif_nb_ventes_mois ?? 0,
                    'objectif_montant_recharges_mois' => $t?->objectif_montant_recharges_mois ?? 0,
                ];
            });

        return Inertia::render('monetique/Parametrage/SeuilsStock', [
            'min_stock_central' => $central?->min_cards ?? 0,
            'objectif_nb_ventes_central' => $central?->objectif_nb_ventes_mois ?? 0,
            'objectif_montant_recharges_central' => $central?->objectif_montant_recharges_mois ?? 0,
            'seuils_agences' => $seuilsAgences,
        ]);
    }

    public function update(Request $request)
    {
        if (! CoficarteAgenceAccess::canViewAll($request->user())) {
            abort(403);
        }

        $validated = $request->validate([
            'min_stock_central' => 'required|integer|min:0',
            'objectif_nb_ventes_central' => 'required|integer|min:0',
            'objectif_montant_recharges_central' => 'required|integer|min:0',
            'agences' => 'nullable|array',
            'agences.*.agence_id' => 'required|exists:agences,id',
            'agences.*.min_cards' => 'required|integer|min:0',
            'agences.*.objectif_nb_ventes_mois' => 'required|integer|min:0',
            'agences.*.objectif_montant_recharges_mois' => 'required|integer|min:0',
        ]);

        CoficarteStockThreshold::query()->updateOrCreate(
            [
                'cible' => CoficarteStockThreshold::CIBLE_CENTRAL,
                'agence_id' => null,
            ],
            [
                'min_cards' => $validated['min_stock_central'],
                'objectif_nb_ventes_mois' => $validated['objectif_nb_ventes_central'],
                'objectif_montant_recharges_mois' => $validated['objectif_montant_recharges_central'],
            ]
        );

        if (! empty($validated['agences'])) {
            foreach ($validated['agences'] as $row) {
                CoficarteStockThreshold::query()->updateOrCreate(
                    [
                        'cible' => CoficarteStockThreshold::CIBLE_AGENCE,
                        'agence_id' => $row['agence_id'],
                    ],
                    [
                        'min_cards' => $row['min_cards'],
                        'objectif_nb_ventes_mois' => $row['objectif_nb_ventes_mois'],
                        'objectif_montant_recharges_mois' => $row['objectif_montant_recharges_mois'],
                    ]
                );
            }
        }

        return redirect()->back()->with('success', 'Seuils et objectifs enregistrés.');
    }
}
