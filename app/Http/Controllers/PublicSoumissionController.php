<?php

namespace App\Http\Controllers;

use App\Models\AppelOffre;
use App\Models\Fournisseur;
use App\Models\Offre;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicSoumissionController extends Controller
{
    public function create(Request $request, AppelOffre $appelOffre, Fournisseur $fournisseur)
    {
        if (! $request->hasValidSignature()) {
            abort(401, 'Ce lien est invalide ou a expiré.');
        }

        if (now()->isAfter($appelOffre->date_limite_soumission)) {
            abort(403, 'La date limite de soumission pour cet appel d\'offres est dépassée.');
        }

        // Vérifier si le fournisseur a déjà soumis
        $hasSubmitted = Offre::where('appel_offre_id', $appelOffre->id)
            ->where('fournisseur_id', $fournisseur->id)
            ->exists();

        return Inertia::render('Public/Soumission', [
            'appelOffre' => $appelOffre,
            'fournisseur' => $fournisseur,
            'hasSubmitted' => $hasSubmitted,
        ]);
    }

    public function store(Request $request, AppelOffre $appelOffre, Fournisseur $fournisseur)
    {
        if (! $request->hasValidSignature()) {
            abort(401, 'Ce lien est invalide ou a expiré.');
        }

        if (now()->isAfter($appelOffre->date_limite_soumission)) {
            abort(403, 'La date limite de soumission est dépassée.');
        }

        // Empêcher les doublons
        if (Offre::where('appel_offre_id', $appelOffre->id)->where('fournisseur_id', $fournisseur->id)->exists()) {
            return redirect()->back()->withErrors(['error' => 'Vous avez déjà soumis une offre pour cet appel.']);
        }

        $request->validate([
            'montant' => 'required|numeric|min:0',
            'articles' => 'required|array|min:1',
            'articles.*.designation' => 'required|string',
            'articles.*.quantite' => 'required|numeric|min:1',
            'articles.*.prix_unitaire_ht' => 'required|numeric|min:0',
            'offre_technique' => 'required|file|mimes:pdf,zip|max:10240', // 10MB max
            'offre_financiere' => 'required|file|mimes:pdf,xlsx,xls,zip|max:10240',
            'ninea' => 'nullable|file|mimes:pdf,jpg,png|max:5120',
            'rccm' => 'nullable|file|mimes:pdf,jpg,png|max:5120',
            'commentaires' => 'nullable|string|max:2000',
        ]);

        $offreTechPath = $request->file('offre_technique')->store('offres/' . $appelOffre->id . '/technique', 'local');
        $offreFinPath = $request->file('offre_financiere')->store('offres/' . $appelOffre->id . '/financiere', 'local');
        
        $nineaPath = $request->hasFile('ninea') ? $request->file('ninea')->store('offres/' . $appelOffre->id . '/admin', 'local') : null;
        $rccmPath = $request->hasFile('rccm') ? $request->file('rccm')->store('offres/' . $appelOffre->id . '/admin', 'local') : null;

        Offre::create([
            'appel_offre_id' => $appelOffre->id,
            'fournisseur_id' => $fournisseur->id,
            'date_soumission' => now(),
            'statut' => 'soumis',
            'montant' => $request->montant,
            'details_financiers' => $request->articles,
            'commentaires' => $request->commentaires,
            'offre_technique_path' => $offreTechPath,
            'offre_financiere_path' => $offreFinPath,
            'ninea_path' => $nineaPath,
            'rccm_path' => $rccmPath,
        ]);

        return redirect()->back()->with('success', 'Votre offre a été soumise avec succès.');
    }
}
