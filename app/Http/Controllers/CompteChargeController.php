<?php

namespace App\Http\Controllers;

use App\Models\CompteCharge;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CompteChargeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $comptesCharges = CompteCharge::latest()->paginate(10);
        return Inertia::render('ComptesCharges/Index', [
            'comptesCharges' => $comptesCharges
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'code_agence' => 'nullable|string|max:255',
            'code_gl' => 'nullable|string|max:255',
        ]);

        CompteCharge::create($validated);
        return redirect()->back()->with('success', 'Compte de charge ajouté avec succès.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CompteCharge $compteCharge)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'code_agence' => 'nullable|string|max:255',
            'code_gl' => 'nullable|string|max:255',
        ]);

        $compteCharge->update($validated);
        return redirect()->back()->with('success', 'Compte de charge mis à jour avec succès.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CompteCharge $compteCharge)
    {
        $compteCharge->delete();
        return redirect()->back()->with('success', 'Compte de charge supprimé avec succès.');
    }
}
