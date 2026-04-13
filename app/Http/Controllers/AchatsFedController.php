<?php

namespace App\Http\Controllers;

use App\Models\Fed;
use App\Models\FedFournisseurOffre;
use App\Models\FedFournisseurOffreAttachment;
use App\Models\Fournisseur;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AchatsFedController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);
        $status = $request->get('status');

        $feds = Fed::with(['requester'])
            ->whereIn('status', [
                Fed::STATUS_N1_APPROVED,
                Fed::STATUS_ACHATS_NEEDS_INFO,
                Fed::STATUS_ACHATS_REJECTED,
                Fed::STATUS_ACHATS_APPROVED,
                Fed::STATUS_FACILITIES_NEEDS_INFO,
                Fed::STATUS_FACILITIES_REJECTED,
                Fed::STATUS_FACILITIES_APPROVED,
                Fed::STATUS_EXPERT_OPINION_PENDING,
                Fed::STATUS_EXPERT_OPINION_GIVEN,
                Fed::STATUS_CG_TREATED,
                Fed::STATUS_WAITING_DAF_RECLASS_APPROVAL,
                Fed::STATUS_DAF_REJECTED,
                Fed::STATUS_DAF_APPROVED,
                Fed::STATUS_DGA_REJECTED,
                Fed::STATUS_BON_DE_COMMANDE,
            ])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByRaw("CASE WHEN status = 'n1_approved' THEN 0 WHEN status = 'achats_needs_info' THEN 1 ELSE 2 END")
            ->orderByDesc('n1_action_at')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return Inertia::render('feds/Facilities/AchatsIndex', [
            'feds' => $feds,
            'selectedStatus' => $status,
        ]);
    }

    public function show(Request $request, Fed $fed)
    {
        $this->assertViewable($fed);

        $fed->load(['items.budgetLine', 'attachments', 'requester', 'budgetLines', 'fournisseurOffres']);

        return Inertia::render('feds/Facilities/AchatsShow', [
            'fed' => $fed,
        ]);
    }

    public function cotation(Request $request, Fed $fed)
    {
        $this->assertViewable($fed);

        $fed->load(['fournisseurOffres.attachments', 'items.budgetLine']);
        $fournisseurs = Fournisseur::orderBy('nom')->get(['id', 'nom', 'type', 'categorie']);

        return Inertia::render('feds/Facilities/AchatsCotation', [
            'fed' => $fed,
            'fournisseurs' => $fournisseurs,
        ]);
    }

    public function storeOffres(Request $request, Fed $fed)
    {
        $this->assertEditableByAchats($fed);

        $offresInput = $request->input('offres');
        if (is_string($offresInput)) {
            $offresInput = json_decode($offresInput, true) ?? [];
        }
        $request->merge(['offres' => $offresInput]);

        $validated = $request->validate([
            'offres' => 'required|array|min:1',
            'offres.*.fournisseur' => 'nullable|string|max:255',
            'offres.*.fournisseur_id' => 'nullable|integer|exists:fournisseurs,id',
            'offres.*.fed_item_id' => 'nullable|integer|exists:fed_items,id',
            'offres.*.prix_unitaire' => 'nullable|numeric|min:0',
            'offres.*.delais_livraison' => 'nullable|string|max:255',
            'offres.*.garanties_offertes' => 'nullable|string|max:2000',
            'offres.*.conformite_reglementaire' => 'nullable|string|in:OUI,NON',
            'offres.*.acompte_requis' => 'nullable|string|in:OUI,NON',
            'offres.*.pourcentage_acompte' => 'nullable|numeric|min:0|max:100',
        ]);

        $fed->fournisseurOffres()->delete();

        $createdOffres = [];
        foreach ($validated['offres'] as $i => $offre) {
            $created = FedFournisseurOffre::create([
                'fed_id' => $fed->id,
                'fournisseur' => $offre['fournisseur'] ?? '',
                'fournisseur_id' => $offre['fournisseur_id'] ?? null,
                'fed_item_id' => $offre['fed_item_id'] ?? null,
                'prix_unitaire' => $offre['prix_unitaire'] ?? null,
                'delais_livraison' => $offre['delais_livraison'] ?? null,
                'garanties_offertes' => $offre['garanties_offertes'] ?? null,
                'conformite_reglementaire' => $offre['conformite_reglementaire'] ?? null,
                'acompte_requis' => $offre['acompte_requis'] ?? null,
                'pourcentage_acompte' => $offre['pourcentage_acompte'] ?? null,
                'ordre' => $i,
            ]);
            $createdOffres[$i] = $created;
        }

        foreach ($request->all() as $key => $value) {
            if (preg_match('/^file_(\d+)$/', $key, $m) && $request->hasFile($key)) {
                $index = (int) $m[1];
                $file = $request->file($key);
                if ($file->isValid() && isset($createdOffres[$index])) {
                    $path = $file->store("feds/{$fed->id}/offres", 'public');
                    FedFournisseurOffreAttachment::create([
                        'fed_fournisseur_offre_id' => $createdOffres[$index]->id,
                        'original_name' => $file->getClientOriginalName(),
                        'path' => $path,
                        'mime_type' => $file->getClientMimeType(),
                        'size' => $file->getSize(),
                    ]);
                }
            }
        }

        return back()->with('success', 'Tableau comparatif enregistré.');
    }

    public function transmitToFacilities(Request $request, Fed $fed)
    {
        $this->assertTransmittable($fed);

        $validated = $request->validate([
            'comment' => 'nullable|string|max:2000',
        ]);

        $fed->update([
            'status' => Fed::STATUS_ACHATS_APPROVED,
            'achats_comment' => $validated['comment'] ?? null,
            'achats_action_at' => now(),
            'achats_validated_by' => $request->user()->name,
        ]);

        return redirect()->route('feds.achats.index')
            ->with('success', 'Cotation envoyée au responsable Facilities. La FED a été transmise en pièce jointe.');
    }

    public function reject(Request $request, Fed $fed)
    {
        $this->assertEditableByAchats($fed);

        $data = $request->validate(['comment' => 'nullable|string|max:2000']);
        $fed->update([
            'status' => Fed::STATUS_ACHATS_REJECTED,
            'achats_comment' => $data['comment'] ?? null,
            'achats_action_at' => now(),
            'achats_validated_by' => $request->user()->name,
        ]);

        return redirect()->route('feds.achats.show', $fed)
            ->with('success', 'FED rejetée.');
    }

    public function needsInfo(Request $request, Fed $fed)
    {
        $this->assertEditableByAchats($fed);

        $data = $request->validate(['comment' => 'nullable|string|max:2000']);
        $fed->update([
            'status' => Fed::STATUS_ACHATS_NEEDS_INFO,
            'achats_comment' => $data['comment'] ?? null,
            'achats_action_at' => now(),
            'achats_validated_by' => $request->user()->name,
        ]);

        return redirect()->route('feds.achats.show', $fed)
            ->with('success', 'Complément demandé au demandeur.');
    }

    private function assertViewable(Fed $fed): void
    {
        if (!in_array($fed->status, [
            Fed::STATUS_PENDING_VALIDATION,
            Fed::STATUS_N1_NEEDS_INFO,
            Fed::STATUS_N1_REJECTED,
            Fed::STATUS_N1_APPROVED,
            Fed::STATUS_ACHATS_NEEDS_INFO,
            Fed::STATUS_ACHATS_APPROVED,
            Fed::STATUS_ACHATS_REJECTED,
            Fed::STATUS_EXPERT_OPINION_PENDING,
            Fed::STATUS_EXPERT_OPINION_GIVEN,
            Fed::STATUS_FACILITIES_NEEDS_INFO,
            Fed::STATUS_FACILITIES_REJECTED,
            Fed::STATUS_FACILITIES_APPROVED,
            Fed::STATUS_CG_TREATED,
            Fed::STATUS_WAITING_DAF_RECLASS_APPROVAL,
            Fed::STATUS_DAF_REJECTED,
            Fed::STATUS_DAF_APPROVED,
            Fed::STATUS_DGA_REJECTED,
            Fed::STATUS_BON_DE_COMMANDE,
        ], true)) {
            abort(403, "Cette FED n'est pas accessible au responsable Achats.");
        }
    }

    private function assertEditableByAchats(Fed $fed): void
    {
        if (!in_array($fed->status, [Fed::STATUS_N1_APPROVED, Fed::STATUS_ACHATS_NEEDS_INFO], true)) {
            abort(403, "Cette FED n'est plus modifiable par Achats.");
        }
    }

    private function assertTransmittable(Fed $fed): void
    {
        if (!in_array($fed->status, [Fed::STATUS_N1_APPROVED, Fed::STATUS_ACHATS_NEEDS_INFO], true)) {
            abort(403, "Cette FED n'est pas transmissible.");
        }
    }
}
