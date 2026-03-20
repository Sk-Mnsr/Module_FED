<?php

namespace App\Http\Controllers;

use App\Models\FicheIntegration;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\FicheIntegrationExport;
use App\Imports\FicheIntegrationImport;

class FicheIntegrationController extends Controller
{
    public function index()
    {
        $fiches = FicheIntegration::with('user')->latest()->paginate(10);
        return Inertia::render('FicheIntegrations/Index', [
            'fiches' => $fiches
        ]);
    }

    public function export()
    {
        return Excel::download(new FicheIntegrationExport, 'fiches-integration.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:10240',
        ]);

        Excel::import(new FicheIntegrationImport, $request->file('file'));

        return redirect()->back()->with('success', 'Fiches d\'intégration importées avec succès.');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'numero' => 'required|string|max:255',
            'no_batch' => 'required|string|max:255',
            'no_compte' => 'required|string|max:255',
            'sens' => 'required|string|max:255',
            'montant' => 'required|numeric',
            'code_operation' => 'required|integer',
            'date_de_valeur' => 'required|date',
            'code_agence' => 'required|integer',
            'libele_ecriture' => 'nullable|string',
            'annee_comptable' => 'required|string|max:4',
            'mois_comptable' => 'required|string|max:2',
            'montantAPayer' => 'required|numeric',
            'account' => 'required|numeric',
            'relicat' => 'required|numeric',
            'restantAPayer' => 'required|numeric',
            'statut' => 'nullable|string|max:255',
        ]);

        $validated['user_id'] = auth()->id() ?? '1'; // Fallback if not authenticated string expected based on migration

        FicheIntegration::create($validated);
        return redirect()->back()->with('success', 'Fiche d\'intégration ajoutée avec succès.');
    }

    public function update(Request $request, FicheIntegration $ficheIntegration)
    {
        $validated = $request->validate([
            'numero' => 'required|string|max:255',
            'no_batch' => 'required|string|max:255',
            'no_compte' => 'required|string|max:255',
            'sens' => 'required|string|max:255',
            'montant' => 'required|numeric',
            'code_operation' => 'required|integer',
            'date_de_valeur' => 'required|date',
            'code_agence' => 'required|integer',
            'libele_ecriture' => 'nullable|string',
            'annee_comptable' => 'required|string|max:4',
            'mois_comptable' => 'required|string|max:2',
            'montantAPayer' => 'required|numeric',
            'account' => 'required|numeric',
            'relicat' => 'required|numeric',
            'restantAPayer' => 'required|numeric',
            'statut' => 'nullable|string|max:255',
        ]);

        $ficheIntegration->update($validated);
        return redirect()->back()->with('success', 'Fiche d\'intégration mise à jour avec succès.');
    }

    public function destroy(FicheIntegration $ficheIntegration)
    {
        $ficheIntegration->delete();
        return redirect()->back()->with('success', 'Fiche d\'intégration supprimée avec succès.');
    }
}
