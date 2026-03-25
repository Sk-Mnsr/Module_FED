<?php

namespace App\Http\Controllers;

use App\Models\AppelOffre;
use App\Models\CritereAppelOffre;
use App\Models\Fournisseur;
use App\Notifications\InvitationFournisseurNotification;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class AppelOffreController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);
        $appelOffres = AppelOffre::with('creator')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return Inertia::render('AppelOffres/Index', [
            'appelOffres' => $appelOffres,
        ]);
    }

    public function create()
    {
        $fournisseurs = Fournisseur::all(['id', 'nom', 'contact_email']);
        return Inertia::render('AppelOffres/Create', compact('fournisseurs'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'objet' => 'required|string|max:255',
            'description' => 'required|string',
            'date_lancement' => 'nullable|date',
            'date_limite_soumission' => 'required|date',
            'type_publication' => 'required|in:interne,externe',
            'criteres' => 'required|array|min:1',
            'criteres.*.nom' => 'required|string|max:255',
            'criteres.*.ponderation' => 'required|integer|min:1',
            'criteres.*.type' => 'required|string',
            'criteres.*.note_maximale' => 'required|numeric|min:1',
            'fournisseurs' => 'nullable|array',
            'fournisseurs.*' => 'exists:fournisseurs,id',
            'dao_file' => 'nullable|file',
            'cahier_charges_file' => 'nullable|file',
        ]);

        DB::beginTransaction();

        $latest = AppelOffre::latest('id')->first();
        $nextId = $latest ? $latest->id + 1 : 1;
        $reference = 'TDR-' . date('Y') . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

        $daoPath = $request->hasFile('dao_file') ? $request->file('dao_file')->store('appel_offres/dao', 'public') : null;
        $cahierChargesPath = $request->hasFile('cahier_charges_file') ? $request->file('cahier_charges_file')->store('appel_offres/cahier_charges', 'public') : null;

        $appelOffre = AppelOffre::create([
            'reference' => $reference,
            'objet' => $validated['objet'],
            'description' => $validated['description'],
            'date_lancement' => $validated['date_lancement'] ?? null,
            'date_limite_soumission' => $validated['date_limite_soumission'],
            'type_publication' => $validated['type_publication'],
            'creator_id' => auth()->id() ?? 1,
            'statut' => 'brouillon',
            'dao_path' => $daoPath,
            'cahier_charges_path' => $cahierChargesPath,
        ]);

        foreach ($validated['criteres'] as $critere) {
            $appelOffre->criteres()->create([
                'nom' => $critere['nom'],
                'ponderation' => $critere['ponderation'],
                'type' => $critere['type'],
                'note_maximale' => $critere['note_maximale'],
            ]);
        }

        if (!empty($validated['fournisseurs'])) {
            $appelOffre->fournisseurs()->attach($validated['fournisseurs']);
        }

        DB::commit();

        return redirect()->route('appel-offres.index')->with('success', 'Appel d\'offres créé avec succès.');
    }

    public function show(AppelOffre $appelOffre)
    {
        $appelOffre->load(['criteres', 'creator', 'comite', 'fournisseurs']);

        return Inertia::render('AppelOffres/Show', [
            'appelOffre' => $appelOffre,
        ]);
    }

    public function edit(AppelOffre $appelOffre)
    {
        $appelOffre->load(['criteres']);
        return Inertia::render('AppelOffres/Edit', [
            'appelOffre' => $appelOffre,
        ]);
    }

    public function update(Request $request, AppelOffre $appelOffre)
    {
        $validated = $request->validate([
            'objet' => 'required|string|max:255',
            'description' => 'required|string',
            'date_lancement' => 'nullable|date',
            'date_limite_soumission' => 'required|date',
            'type_publication' => 'required|in:interne,externe',
        ]);

        $appelOffre->update($validated);

        return redirect()->route('appel-offres.show', $appelOffre)
            ->with('success', "Appel d'offres mis à jour.");
    }

    public function destroy(AppelOffre $appelOffre)
    {
        if ($appelOffre->statut !== 'brouillon') {
            return redirect()->back()->with('error', "Impossible de supprimer un appel d'offres déjà publié.");
        }

        $appelOffre->delete();
        return redirect()->route('appel-offres.index')->with('success', 'Appel d\'offres supprimé avec succès.');
    }

    public function publish(AppelOffre $appelOffre)
    {
        $appelOffre->update(['statut' => 'publie']);

        // Only notify suppliers if there are any attached
        $appelOffre->load('fournisseurs');
        foreach ($appelOffre->fournisseurs as $fournisseur) {
            if ($fournisseur->contact_email) {
                \Illuminate\Support\Facades\Notification::route('mail', $fournisseur->contact_email)
                    ->notify(new \App\Notifications\InvitationFournisseurNotification($appelOffre, $fournisseur));
            }
        }

        return redirect()->back()->with('success', 'Appel d\'offres publié et invitations envoyées aux fournisseurs.');
    }

    public function openingSession(AppelOffre $appelOffre)
    {
        $appelOffre->load('comite.membres');
        $appelOffre->load(['offres' => function ($query) use ($appelOffre) {
            if (!$appelOffre->is_plis_ouverts) {
                // Return only id and timestamp to maintain anonymity and security
                $query->select('id', 'appel_offre_id', 'created_at');
            }
        }]);

        return \Inertia\Inertia::render('Offres/OpeningSession', [
            'appelOffre' => $appelOffre,
        ]);
    }

    public function startEvaluation(Request $request, AppelOffre $appelOffre)
    {
        $request->validate([
            'cle_ouverture' => 'required|string',
        ]);

        $cleSaisie = strtoupper(str_replace(' ', '', trim($request->cle_ouverture)));

        if (!\Illuminate\Support\Facades\Hash::check($cleSaisie, $appelOffre->cle_ouverture_hash)) {
            return back()->withErrors(['cle_ouverture' => 'La clé d\'ouverture est incorrecte. Vérifiez les fragments fournis.']);
        }

        $appelOffre->update(['statut' => 'en_evaluation', 'is_plis_ouverts' => true]);
        return redirect()->back()->with('success', 'La clé est valide. Les plis sont ouverts et l\'évaluation peut commencer.');
    }

    public function pvOuverture(AppelOffre $appelOffre)
    {
        $appelOffre->load('offres', 'comite.membres');
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.pv_ouverture', compact('appelOffre'));
        return $pdf->download('PV_Ouverture_' . $appelOffre->reference . '.pdf');
    }
}
