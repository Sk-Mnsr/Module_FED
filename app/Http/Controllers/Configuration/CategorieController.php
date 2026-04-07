<?php

namespace App\Http\Controllers\Configuration;

use App\Http\Controllers\Controller;
use App\Models\Categorie;
use Illuminate\Http\Request;

class CategorieController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom'        => 'required|string|max:255',
            'famille_id' => 'required|exists:familles,id',
        ]);
        Categorie::create($validated);
        return redirect()->back()->with('success', 'Catégorie créée avec succès.');
    }

    public function update(Request $request, Categorie $categorie)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
        ]);
        $categorie->update($validated);
        return redirect()->back()->with('success', 'Catégorie mise à jour.');
    }

    public function destroy(Categorie $categorie)
    {
        $categorie->delete();
        return redirect()->back()->with('success', 'Catégorie supprimée.');
    }
}
