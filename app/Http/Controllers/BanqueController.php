<?php

namespace App\Http\Controllers;

use App\Models\Banque;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BanqueController extends Controller
{
    public function index()
    {
        $banques = Banque::latest()->paginate(10);
        return Inertia::render('Banques/Index', [
            'banques' => $banques
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'compte_miroir' => 'nullable|string|max:255',
            'compte_externe' => 'nullable|string|max:255',
        ]);

        Banque::create($validated);
        return redirect()->back()->with('success', 'Banque ajoutée avec succès.');
    }

    public function update(Request $request, Banque $banque)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'compte_miroir' => 'nullable|string|max:255',
            'compte_externe' => 'nullable|string|max:255',
        ]);

        $banque->update($validated);
        return redirect()->back()->with('success', 'Banque mise à jour avec succès.');
    }

    public function destroy(Banque $banque)
    {
        $banque->delete();
        return redirect()->back()->with('success', 'Banque supprimée avec succès.');
    }
}
