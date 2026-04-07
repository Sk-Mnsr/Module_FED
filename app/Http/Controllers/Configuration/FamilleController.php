<?php

namespace App\Http\Controllers\Configuration;

use App\Http\Controllers\Controller;
use App\Models\Famille;
use App\Models\Categorie;
use App\Models\SousCategorie;
use App\Exports\FamilleTemplateExport;
use App\Imports\FamilleImport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class FamilleController extends Controller
{
    public function index()
    {
        $familles = Famille::with(['categories.sousCategories'])->orderBy('nom')->get();

        return Inertia::render('Configuration/Familles/Index', [
            'familles' => $familles,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255|unique:familles,nom',
        ]);
        Famille::create($validated);
        return redirect()->back()->with('success', 'Famille créée avec succès.');
    }

    public function update(Request $request, Famille $famille)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255|unique:familles,nom,' . $famille->id,
        ]);
        $famille->update($validated);
        return redirect()->back()->with('success', 'Famille mise à jour.');
    }

    public function destroy(Famille $famille)
    {
        $famille->delete();
        return redirect()->back()->with('success', 'Famille supprimée.');
    }

    // ─── Import / Export ─────────────────────────────────────────────────────────
    
    public function exportTemplate()
    {
        return Excel::download(new FamilleTemplateExport, 'template_familles_articles.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,csv,xls|max:5120', // Max 5MB
        ]);

        Excel::import(new FamilleImport, $request->file('file'));

        return redirect()->back()->with('success', 'Importation réussie avec succès.');
    }

    // ─── Endpoints JSON pour filtrage dynamique ───────────────────────────────

    public function categories(Famille $famille)
    {
        return response()->json($famille->categories()->orderBy('nom')->get());
    }

    public function sousCategories(Categorie $categorie)
    {
        return response()->json($categorie->sousCategories()->orderBy('nom')->get());
    }
}
