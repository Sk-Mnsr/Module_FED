<?php

namespace App\Http\Controllers;

use App\Models\Fournisseur;
use App\Models\Banque;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FournisseurController extends Controller
{
    public function index()
    {
        $fournisseurs = Fournisseur::with('banque')->latest()->paginate(10);
        $banques = Banque::all();
        
        return Inertia::render('Fournisseurs/Index', [
            'fournisseurs' => $fournisseurs,
            'banques' => $banques
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'type' => 'nullable|string|max:255',
            'categorie' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'contact_nom' => 'nullable|string|max:255',
            'contact_telephone' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'site_web' => 'nullable|string|max:255',
            'adresse_physique' => 'nullable|string',
            'compte_transit_paiement' => ['nullable', 'regex:/^\d{12}$/'],
            'compte_avance_acompte' => ['nullable', 'regex:/^\d{12}$/'],
            'compte_client_interne' => ['nullable', 'regex:/^\d{12}$/'],
            'banque_id' => 'nullable|exists:banques,id',
        ], [
            'compte_transit_paiement.regex' => 'Le compte transit doit contenir exactement 12 chiffres.',
            'compte_avance_acompte.regex' => 'Le compte avance/acompte doit contenir exactement 12 chiffres.',
            'compte_client_interne.regex' => 'Le compte client interne doit contenir exactement 12 chiffres.',
        ]);

        Fournisseur::create($validated);

        return redirect()->back()->with('success', 'Fournisseur ajouté avec succès.');
    }

    public function update(Request $request, Fournisseur $fournisseur)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'type' => 'nullable|string|max:255',
            'categorie' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'contact_nom' => 'nullable|string|max:255',
            'contact_telephone' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'site_web' => 'nullable|string|max:255',
            'adresse_physique' => 'nullable|string',
            'compte_transit_paiement' => ['nullable', 'regex:/^\d{12}$/'],
            'compte_avance_acompte' => ['nullable', 'regex:/^\d{12}$/'],
            'compte_client_interne' => ['nullable', 'regex:/^\d{12}$/'],
            'banque_id' => 'nullable|exists:banques,id',
        ], [
            'compte_transit_paiement.regex' => 'Le compte transit doit contenir exactement 12 chiffres.',
            'compte_avance_acompte.regex' => 'Le compte avance/acompte doit contenir exactement 12 chiffres.',
            'compte_client_interne.regex' => 'Le compte client interne doit contenir exactement 12 chiffres.',
        ]);

        $fournisseur->update($validated);

        return redirect()->back()->with('success', 'Fournisseur mis à jour avec succès.');
    }

    public function destroy(Fournisseur $fournisseur)
    {
        $fournisseur->delete();
        return redirect()->back()->with('success', 'Fournisseur supprimé avec succès.');
    }
}
