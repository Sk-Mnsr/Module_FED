<?php

namespace App\Http\Controllers\Configuration;

use App\Http\Controllers\Controller;
use App\Models\SousCategorie;
use Illuminate\Http\Request;

class SousCategorieController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom'          => 'required|string|max:255',
            'categorie_id' => 'required|exists:categories,id',
        ]);
        SousCategorie::create($validated);
        return redirect()->back()->with('success', 'Sous-catégorie créée avec succès.');
    }

    public function update(Request $request, SousCategorie $sousCategorie)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
        ]);
        $sousCategorie->update($validated);
        return redirect()->back()->with('success', 'Sous-catégorie mise à jour.');
    }

    public function destroy(SousCategorie $sousCategorie)
    {
        $sousCategorie->delete();
        return redirect()->back()->with('success', 'Sous-catégorie supprimée.');
    }
}
