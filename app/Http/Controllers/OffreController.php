<?php

namespace App\Http\Controllers;

use App\Models\AppelOffre;
use App\Models\Offre;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class OffreController extends Controller
{
    public function create(AppelOffre $appelOffre)
    {
        if ($appelOffre->statut !== 'publie') {
            return redirect()->back()->with('error', "Cet appel d'offres n'est pas ouvert aux soumissions.");
        }

        return Inertia::render('Offres/Submit', [
            'appelOffre' => $appelOffre,
        ]);
    }

    public function store(Request $request, AppelOffre $appelOffre)
    {
        if ($appelOffre->statut !== 'publie' || now() > $appelOffre->date_limite_soumission) {
            return redirect()->back()->withErrors(['error' => 'La période de soumission est close ou l\'appel n\'est pas publié.']);
        }

        $validated = $request->validate([
            'nom_fournisseur' => 'required|string|max:255',
            'email_fournisseur' => 'required|email|max:255',
            'contact_nom' => 'nullable|string|max:255',
            'contact_telephone' => 'nullable|string|max:255',
            'offre_technique' => 'required|file',
            'offre_financiere' => 'required|file',
            'rccm_file' => 'nullable|file',
            'ninea_file' => 'nullable|file',
            'fiche_technique_file' => 'nullable|file',
            'references_file' => 'nullable|file',
            'montant' => 'required|numeric',
            'details_financiers' => 'required|array',
            'commentaires' => 'nullable|string|max:2000',
        ]);

        // Simuler un dossier upload pour chaque offre
        $offreId = mt_rand(1000, 9999);
        $baseDir = "offres/{$appelOffre->id}/{$offreId}";

        // Note: l'idée c'est de stocker en BD les chemins. On n'a pas mis les chemins dans la BD pour l'offre tech/fin ?
        // Ah, je vais utiliser "rccm_path" par exemple.
        $rccmPath = $request->hasFile('rccm_file') ? $request->file('rccm_file')->store($baseDir, 'public') : null;
        $nineaPath = $request->hasFile('ninea_file') ? $request->file('ninea_file')->store($baseDir, 'public') : null;
        $ficheTechniquePath = $request->hasFile('fiche_technique_file') ? $request->file('fiche_technique_file')->store($baseDir, 'public') : null;
        $referencesPath = $request->hasFile('references_file') ? $request->file('references_file')->store($baseDir, 'public') : null;

        $offre = Offre::create([
            'appel_offre_id' => $appelOffre->id,
            'user_id' => auth()->check() ? auth()->id() : null, // Si c'est un fournisseur logué (ou null si lien public)
            'nom_fournisseur' => $validated['nom_fournisseur'],
            'email_fournisseur' => $validated['email_fournisseur'],
            'contact_nom' => $validated['contact_nom'] ?? null,
            'contact_telephone' => $validated['contact_telephone'] ?? null,
            'date_soumission' => now(),
            'statut' => 'soumis',
            'montant' => $validated['montant'],
            'details_financiers' => $validated['details_financiers'],
            'commentaires' => $validated['commentaires'] ?? null,
            'rccm_path' => $rccmPath,
            'ninea_path' => $nineaPath,
            'fiche_technique_path' => $ficheTechniquePath,
            'references_path' => $referencesPath,
            'offre_technique_path' => $request->hasFile('offre_technique') ? $request->file('offre_technique')->store($baseDir, 'public') : null,
            'offre_financiere_path' => $request->hasFile('offre_financiere') ? $request->file('offre_financiere')->store($baseDir, 'public') : null,
        ]);

        return redirect()->route('appel-offres.show', $appelOffre)->with('success', 'Votre offre a été soumise avec succès.');
    }
}
