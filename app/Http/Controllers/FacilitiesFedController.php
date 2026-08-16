<?php

namespace App\Http\Controllers;

use App\Models\Fed;
use App\Support\AppMail;
use App\Support\Mails\FedWorkflowMail;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FacilitiesFedController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);
        $status = $request->get('status');

        $feds = Fed::with(['requester'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->whereIn('status', [
                Fed::STATUS_ACHATS_APPROVED, 
                Fed::STATUS_FACILITIES_NEEDS_INFO, 
                Fed::STATUS_FACILITIES_APPROVED, 
                Fed::STATUS_FACILITIES_REJECTED,
                Fed::STATUS_EXPERT_OPINION_PENDING,
                Fed::STATUS_EXPERT_OPINION_GIVEN
            ])
            ->orderByDesc('achats_action_at')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return Inertia::render('feds/Facilities/FacilitiesIndex', [
            'feds' => $feds,
            'selectedStatus' => $status,
        ]);
    }

    public function show(Request $request, Fed $fed)
    {
        $this->assertViewable($fed);

        $fed->load(['items.budgetLine', 'attachments', 'requester', 'budgetLines', 'fournisseurOffres.attachments']);

        return Inertia::render('feds/Facilities/FacilitiesShow', [
            'fed' => $fed,
        ]);
    }

    public function approve(Request $request, Fed $fed)
    {
        $this->assertValidatable($fed);

        $data = $request->validate([
            'comment' => 'required|string|max:2000',
            'offre_choisie_id' => 'nullable|integer',
            'request_expert_opinion' => 'nullable|boolean',
        ], ['comment.required' => 'Votre avis est obligatoire pour valider.']);

        $status = $request->boolean('request_expert_opinion') 
            ? Fed::STATUS_EXPERT_OPINION_PENDING 
            : Fed::STATUS_FACILITIES_APPROVED;

        $updateData = [
            'status' => $status,
            'expert_opinion_requested' => $request->boolean('request_expert_opinion'),
            'facilities_comment' => $data['comment'] ?? null,
            'facilities_action_at' => now(),
        ];
        if (!empty($data['offre_choisie_id']) && $fed->fournisseurOffres()->where('id', $data['offre_choisie_id'])->exists()) {
            $updateData['offre_choisie_id'] = $data['offre_choisie_id'];
        }
        $fed->update($updateData);
        $fed->update(['facilities_validated_by' => $request->user()->name]);

        if ($request->boolean('request_expert_opinion')) {
            $fed->loadMissing('requester.nPlus1', 'requester.department.manager');
            $n1 = $fed->requester?->resolveNPlus1();
            if ($n1 !== null) {
                AppMail::notify($n1, 'Avis expert demandé — '.$fed->referenceLabel(), [
                    'title' => 'Avis expert métier requis',
                    'content' => 'Facilities demande votre avis expert sur cette FED.',
                    'action_required' => 'Rendre votre avis dans la file N+1.',
                    'details' => ['Référence' => $fed->referenceLabel()],
                    'action_url' => url('/feds/n1/'.$fed->id),
                    'action_text' => 'Donner mon avis',
                ]);
            }
        } else {
            FedWorkflowMail::notifyRole(
                $fed,
                'controle_de_gestion',
                'FED à traiter (Contrôle de gestion) — '.$fed->referenceLabel(),
                'FED validée par Facilities',
                'Contrôle budgétaire à effectuer.',
                url('/feds/cg/'.$fed->id),
            );
        }

        return redirect()->route('feds.facilities.show', $fed)
            ->with('success', $request->boolean('request_expert_opinion') ? 'FED transmise pour avis expert métier.' : 'Offre choisie et FED validée.');
    }

    public function reject(Request $request, Fed $fed)
    {
        $this->assertValidatable($fed);

        $data = $request->validate(['comment' => 'required|string|max:2000'], ['comment.required' => 'Votre avis est obligatoire pour rejeter.']);
        $fed->update([
            'status' => Fed::STATUS_FACILITIES_REJECTED,
            'facilities_comment' => $data['comment'] ?? null,
            'facilities_action_at' => now(),
            'facilities_validated_by' => $request->user()->name,
        ]);

        FedWorkflowMail::toRequesterRejected($fed, 'Facilities', $data['comment'] ?? null);

        return redirect()->route('feds.facilities.show', $fed)
            ->with('success', 'FED rejetée par Facilities.');
    }

    public function needsInfo(Request $request, Fed $fed)
    {
        $this->assertValidatable($fed);

        $data = $request->validate(['comment' => 'required|string|max:2000'], ['comment.required' => 'Votre avis est obligatoire pour mettre en attente.']);
        $fed->update([
            'status' => Fed::STATUS_FACILITIES_NEEDS_INFO,
            'facilities_comment' => $data['comment'],
            'facilities_action_at' => now(),
        ]);

        FedWorkflowMail::notifyRole(
            $fed,
            'responsable_achats',
            'Complément demandé (Facilities) — '.$fed->referenceLabel(),
            'Complément demandé par Facilities',
            $data['comment'],
            url('/feds/achats/'.$fed->id),
        );

        return redirect()->route('feds.facilities.index')
            ->with('success', 'FED mise en attente.');
    }

    private function assertViewable(Fed $fed): void
    {
        if (!in_array($fed->status, [
            Fed::STATUS_ACHATS_APPROVED,
            Fed::STATUS_FACILITIES_NEEDS_INFO,
            Fed::STATUS_FACILITIES_APPROVED,
            Fed::STATUS_FACILITIES_REJECTED,
            Fed::STATUS_EXPERT_OPINION_PENDING,
            Fed::STATUS_EXPERT_OPINION_GIVEN,
        ], true)) {
            abort(403, "Cette FED n'est pas accessible au responsable Facilities.");
        }
    }

    private function assertValidatable(Fed $fed): void
    {
        if (!in_array($fed->status, [
            Fed::STATUS_ACHATS_APPROVED, 
            Fed::STATUS_FACILITIES_NEEDS_INFO,
            Fed::STATUS_EXPERT_OPINION_GIVEN
        ], true)) {
            abort(403, "Cette FED n'est pas en attente de validation Facilities.");
        }
    }
}
