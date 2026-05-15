<?php

namespace App\Http\Controllers\Configuration;

use App\Http\Controllers\Controller;
use App\Models\CoficarteApporteur;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ApporteurAffaireController extends Controller
{
    public function index()
    {
        $apporteurs = CoficarteApporteur::query()
            ->whereNull('agence_id')
            ->orderBy('nom')
            ->paginate(15)
            ->through(fn (CoficarteApporteur $a) => [
                'id' => $a->id,
                'code' => $a->code,
                'nom' => $a->nom,
                'actif' => (bool) $a->actif,
            ]);

        return Inertia::render('Configuration/ApporteursAffaires/Index', [
            'apporteurs' => $apporteurs,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64', Rule::unique('coficarte_apporteurs', 'code')],
            'nom' => 'required|string|max:191',
        ]);

        CoficarteApporteur::create([
            'agence_id' => null,
            'code' => $validated['code'],
            'nom' => $validated['nom'],
            'telephone' => null,
            'email' => null,
            'actif' => true,
        ]);

        return redirect()->back()->with('success', 'Apporteur d’affaires créé.');
    }

    public function update(Request $request, CoficarteApporteur $coficarteApporteur)
    {
        if ($coficarteApporteur->agence_id !== null) {
            abort(404);
        }

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('coficarte_apporteurs', 'code')->ignore($coficarteApporteur->id),
            ],
            'nom' => 'required|string|max:191',
        ]);

        $coficarteApporteur->update([
            ...$validated,
            'actif' => $request->boolean('actif'),
        ]);

        return redirect()->back()->with('success', 'Apporteur d’affaires mis à jour.');
    }

    public function destroy(CoficarteApporteur $coficarteApporteur)
    {
        if ($coficarteApporteur->agence_id !== null) {
            abort(404);
        }

        $coficarteApporteur->update(['actif' => false]);

        return redirect()->back()->with('success', 'Apporteur d’affaires désactivé.');
    }
}
