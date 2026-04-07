<?php

namespace App\Http\Controllers\Configuration;

use App\Http\Controllers\Controller;
use App\Models\Agence;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgenceController extends Controller
{
    public function index()
    {
        $agences = Agence::latest()->paginate(10);
        return Inertia::render('Configuration/Agences/Index', [
            'agences' => $agences,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:agences,code',
        ]);

        Agence::create($validated);
        return redirect()->back()->with('success', 'Agence créée avec succès.');
    }

    public function update(Request $request, Agence $agence)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:agences,code,' . $agence->id,
        ]);

        $agence->update($validated);
        return redirect()->back()->with('success', 'Agence mise à jour avec succès.');
    }

    public function destroy(Agence $agence)
    {
        $agence->delete();
        return redirect()->back()->with('success', 'Agence supprimée avec succès.');
    }
}
