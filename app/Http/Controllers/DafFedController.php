<?php

namespace App\Http\Controllers;

use App\Models\Fed;
use App\Models\AppSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DafFedController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);
        $status = $request->get('status');
        $tab = $request->get('tab', 'validation'); // 'validation' ou 'reclassement'

        $fedsQuery = Fed::query()->with(['requester', 'fournisseurOffres', 'items'])
            ->orderByDesc('facilities_action_at')
            ->orderByDesc('created_at');

        if ($tab === 'reclassement') {
            $fedsQuery->where('status', '=', Fed::STATUS_WAITING_DAF_RECLASS_APPROVAL);
        } else {
            // tab = validation standard
            $fedsQuery->whereIn('status', [
                Fed::STATUS_CG_TREATED,
                Fed::STATUS_DAF_APPROVED,
                Fed::STATUS_DAF_REJECTED,
            ])->when($status, fn ($q) => $q->where('status', $status));
        }

        $feds = $fedsQuery->paginate($perPage);

        return Inertia::render('feds/Daf/DafIndex', [
            'feds' => $feds,
            'selectedStatus' => $status,
            'activeTab' => $tab,
        ]);
    }

    public function show(Request $request, Fed $fed)
    {
        $this->assertViewable($fed);

        $fed->load([
            'items.budgetLine',
            'attachments',
            'requester',
            'budgetLines',
            'fournisseurOffres.attachments',
            'budgetLineHistories.user',
            'budgetLineHistories.fromLine',
            'budgetLineHistories.toLine',
        ]);

        $dgaThreshold = (float) AppSetting::get('fed_dga_threshold', 0);

        return Inertia::render('feds/Daf/DafShow', [
            'fed'          => $fed,
            'dgaThreshold' => $dgaThreshold,
        ]);
    }

    public function approve(Request $request, Fed $fed)
    {
        $this->assertValidatable($fed);

        $data = $request->validate(['comment' => 'nullable|string|max:2000']);

        // Routage conditionnel selon le seuil paramétrable
        $dgaThreshold = (float) AppSetting::get('fed_dga_threshold', 0);
        $fedTotal     = (float) $fed->estimated_total;

        // Si seuil actif ET montant dépasse le seuil → transmission au DGA
        $needsDga  = $dgaThreshold > 0 && $fedTotal >= $dgaThreshold;
        $newStatus = $needsDga ? Fed::STATUS_DAF_APPROVED : Fed::STATUS_BON_DE_COMMANDE;

        $fed->update([
            'status'           => $newStatus,
            'daf_comment'      => $data['comment'] ?? null,
            'daf_action_at'    => now(),
            'daf_validated_by' => $request->user()->name,
        ]);

        $message = $needsDga
            ? 'FED validée par le DAF. Transmise au DGA pour validation finale.'
            : 'FED validée par le DAF. Bon de commande généré directement (en dessous du seuil DGA).';

        return redirect()->route('feds.daf.index')->with('success', $message);
    }

    public function reject(Request $request, Fed $fed)
    {
        $this->assertValidatable($fed);

        $data = $request->validate(['comment' => 'nullable|string|max:2000']);
        $fed->update([
            'status' => Fed::STATUS_DAF_REJECTED,
            'daf_comment' => $data['comment'] ?? null,
            'daf_action_at' => now(),
            'daf_validated_by' => $request->user()->name,
        ]);

        return redirect()->route('feds.daf.index')
            ->with('success', 'FED rejetée par le DAF.');
    }

    public function approveReclass(Request $request, Fed $fed)
    {
        if ($fed->status !== Fed::STATUS_WAITING_DAF_RECLASS_APPROVAL) {
            abort(403, "Cette FED n'est pas en attente de validation de reclassement.");
        }

        $data = $request->validate(['comment' => 'nullable|string|max:2000']);

        // Trouver la demande de reclassement en attente
        $history = \App\Models\BudgetLineHistory::where('fed_id', $fed->id)
            ->where('status', 'pending')
            ->where('action', 'transfer_amount')
            ->firstOrFail();

        \Illuminate\Support\Facades\DB::beginTransaction();

        $fromLine = $history->fromLine;
        $toLine = $history->toLine;
        $montant = $history->montant_transfere;

        // Effectuer le transfert effectif
        $fromLine->update([
            'montant_estime'  => max(0, $fromLine->montant_estime - $montant),
            'is_reclassified' => true,
        ]);

        $toLine->update([
            'montant_estime'  => $toLine->montant_estime + $montant,
            'is_reclassified' => true,
        ]);

        $fromLine->budget->update([
            'total_amount' => $fromLine->budget->lines()->sum('montant_estime'),
        ]);

        // Mettre à jour l'historique
        $history->update([
            'from_montant_after' => $fromLine->montant_estime,
            'to_montant_after'   => $toLine->montant_estime,
            'status'             => 'approved',
        ]);

        $fed->update([
            // Repasser la main au CG ou considérer comme traité par le CG pour passer au DAF validation classique
            'status' => Fed::STATUS_CG_TREATED, 
            'daf_comment' => $data['comment'] ?? null,
            'daf_action_at' => now(),
            'daf_validated_by' => $request->user()->name,
        ]);

        \Illuminate\Support\Facades\DB::commit();

        return redirect()->route('feds.daf.index', ['tab' => 'reclassement'])
            ->with('success', 'Reclassement approuvé. La FED continue son cycle de validation.');
    }

    public function rejectReclass(Request $request, Fed $fed)
    {
        if ($fed->status !== Fed::STATUS_WAITING_DAF_RECLASS_APPROVAL) {
            abort(403, "Cette FED n'est pas en attente de validation de reclassement.");
        }

        $data = $request->validate(['comment' => 'required|string|max:2000'], [
            'comment.required' => 'Veuillez saisir un motif de rejet.',
        ]);

        $history = \App\Models\BudgetLineHistory::where('fed_id', $fed->id)
            ->where('status', 'pending')
            ->where('action', 'transfer_amount')
            ->firstOrFail();

        \Illuminate\Support\Facades\DB::beginTransaction();

        $history->update([
            'status' => 'rejected',
        ]);

        $fed->update([
            'status' => Fed::STATUS_FACILITIES_APPROVED, // Remise à la file d'attente du CG
            'daf_comment' => $data['comment'],
            'daf_action_at' => now(),
            'daf_validated_by' => $request->user()->name,
        ]);

        \Illuminate\Support\Facades\DB::commit();

        return redirect()->route('feds.daf.index', ['tab' => 'reclassement'])
            ->with('success', 'Reclassement rejeté. La FED retourne au Contrôle de Gestion.');
    }

    private function assertViewable(Fed $fed): void
    {
        if (!in_array($fed->status, [
            Fed::STATUS_WAITING_DAF_RECLASS_APPROVAL,
            Fed::STATUS_CG_TREATED,
            Fed::STATUS_DAF_APPROVED,
            Fed::STATUS_DAF_REJECTED,
        ], true)) {
            abort(403, "Cette FED n'est pas accessible au DAF.");
        }
    }

    private function assertValidatable(Fed $fed): void
    {
        if ($fed->status !== Fed::STATUS_CG_TREATED) {
            abort(403, "Cette FED n'est pas en attente de validation DAF.");
        }
    }
}
