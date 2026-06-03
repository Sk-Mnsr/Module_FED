<?php

namespace App\Http\Controllers\Monetique;

use App\Http\Controllers\Controller;
use App\Models\CoficarteApporteur;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ApporteurController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('ca') || $user->agence_id === null) {
            abort(403);
        }

        $apporteurs = CoficarteApporteur::query()
            ->where('agence_id', $user->agence_id)
            ->orderBy('nom')
            ->get(['id', 'nom', 'telephone', 'email', 'actif', 'code']);

        return Inertia::render('monetique/Agence/Apporteurs', [
            'apporteurs' => $apporteurs,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('ca') || $user->agence_id === null) {
            abort(403);
        }

        $validated = $request->validate([
            'nom' => 'required|string|max:191',
            'telephone' => 'nullable|string|max:64',
            'email' => 'nullable|email|max:191',
        ]);

        CoficarteApporteur::create([
            'agence_id' => $user->agence_id,
            'nom' => $validated['nom'],
            'telephone' => $validated['telephone'] ?? null,
            'email' => $validated['email'] ?? null,
            'actif' => true,
        ]);

        return redirect()->back()->with('success', 'Apporteur ajouté.');
    }

    public function destroy(Request $request, CoficarteApporteur $coficarteApporteur)
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('ca') || (int) $coficarteApporteur->agence_id !== (int) $user->agence_id) {
            abort(403);
        }

        $coficarteApporteur->update(['actif' => false]);

        return redirect()->back()->with('success', 'Apporteur désactivé.');
    }
}
