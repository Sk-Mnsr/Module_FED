<?php

namespace App\Http\Controllers;

use App\Models\Fed;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DgaFedController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);
        $status = $request->get('status');

        $feds = Fed::with(['requester'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->whereIn('status', [Fed::STATUS_DAF_APPROVED, Fed::STATUS_DGA_REJECTED, Fed::STATUS_BON_DE_COMMANDE])
            ->orderByDesc('daf_action_at')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return Inertia::render('feds/Dga/DgaIndex', [
            'feds' => $feds,
            'selectedStatus' => $status,
        ]);
    }

    public function show(Request $request, Fed $fed)
    {
        $this->assertViewable($fed);

        $fed->load(['items.budgetLine', 'attachments', 'requester', 'budgetLines', 'fournisseurOffres.attachments']);

        return Inertia::render('feds/Dga/DgaShow', [
            'fed' => $fed,
        ]);
    }

    public function approve(Request $request, Fed $fed)
    {
        $this->assertValidatable($fed);

        $data = $request->validate(['comment' => 'nullable|string|max:2000']);

        $fed->update([
            'status' => Fed::STATUS_BON_DE_COMMANDE,
            'dga_comment' => $data['comment'] ?? null,
            'dga_action_at' => now(),
            'dga_validated_by' => $request->user()->name,
        ]);

        return redirect()->route('feds.dga.index')
            ->with('success', 'FED validée par le DGA. Prête pour le bon de commande.');
    }

    public function reject(Request $request, Fed $fed)
    {
        $this->assertValidatable($fed);

        $data = $request->validate(['comment' => 'nullable|string|max:2000']);
        $fed->update([
            'status' => Fed::STATUS_DGA_REJECTED,
            'dga_comment' => $data['comment'] ?? null,
            'dga_action_at' => now(),
            'dga_validated_by' => $request->user()->name,
        ]);

        return redirect()->route('feds.dga.index')
            ->with('success', 'FED rejetée par le DGA.');
    }

    private function assertViewable(Fed $fed): void
    {
        if (!in_array($fed->status, [
            Fed::STATUS_DAF_APPROVED,
            Fed::STATUS_DGA_REJECTED,
            Fed::STATUS_BON_DE_COMMANDE,
        ], true)) {
            abort(403, "Cette FED n'est pas accessible au DGA.");
        }
    }

    private function assertValidatable(Fed $fed): void
    {
        if ($fed->status !== Fed::STATUS_DAF_APPROVED) {
            abort(403, "Cette FED n'est pas en attente de validation DGA.");
        }
    }
}
