<?php

namespace App\Http\Controllers;

use App\Models\TypeDepense;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TypeDepenseController extends Controller
{
    public function index()
    {
        $typeDepenses = TypeDepense::latest()->paginate(20);
        return Inertia::render('TypeDepenses/Index', [
            'typeDepenses' => $typeDepenses,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom_depense' => 'required|string|max:255',
            'compte_gl'   => 'nullable|string|max:255',
        ]);

        TypeDepense::create($validated);
        return redirect()->back()->with('success', 'Type de dépense ajouté avec succès.');
    }

    public function update(Request $request, TypeDepense $typeDepense)
    {
        $validated = $request->validate([
            'nom_depense' => 'required|string|max:255',
            'compte_gl'   => 'nullable|string|max:255',
        ]);

        $typeDepense->update($validated);
        return redirect()->back()->with('success', 'Type de dépense mis à jour avec succès.');
    }

    public function destroy(TypeDepense $typeDepense)
    {
        $typeDepense->delete();
        return redirect()->back()->with('success', 'Type de dépense supprimé avec succès.');
    }
}
