<?php

namespace App\Http\Controllers;

use App\Models\Fed;
use Illuminate\Http\Request;
use Inertia\Inertia;

class N1FedController extends Controller
{
    public function index(Request $request)
    {
        $n1User = $request->user();
        if ($n1User === null) {
            abort(403, 'Utilisateur N+1 introuvable.');
        }

        $n1UserId = $n1User->id;
        $perPage = (int) $request->get('per_page', 10);
        $status = $request->get('status');

        $feds = Fed::with(['requester'])
            ->whereHas('requester', function ($query) use ($n1UserId) {
                $query
                    ->where('n_plus_1_user_id', $n1UserId)
                    ->orWhere(function ($sub) use ($n1UserId) {
                        $sub->whereNull('n_plus_1_user_id')
                            ->whereHas('department', function ($dept) use ($n1UserId) {
                                $dept->where('manager_user_id', $n1UserId);
                            });
                    });
            })
            ->whereNotNull('submitted_at')
            ->whereIn('status', [Fed::STATUS_PENDING_VALIDATION, Fed::STATUS_EXPERT_OPINION_PENDING, Fed::STATUS_N1_APPROVED, Fed::STATUS_N1_REJECTED, Fed::STATUS_N1_NEEDS_INFO])
            ->when($status, function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->orderByDesc('submitted_at')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return Inertia::render('feds/N1Index', [
            'feds' => $feds,
            'selectedStatus' => $status,
        ]);
    }

    public function show(Request $request, Fed $fed)
    {
        $this->authorizeN1($request, $fed);

        $fed->load(['items.budgetLine', 'attachments', 'requester', 'budgetLines', 'fournisseurOffres.attachments']);
        $departmentId = $fed->requester?->department_id;
        $year = $fed->date ? $fed->date->format('Y') : now()->format('Y');

        return Inertia::render('feds/N1Show', [
            'fed' => $fed,
            'authSignature' => $request->user()->signature,
            'budgetContext' => [
                'departmentId' => $departmentId,
                'year' => (int) $year,
            ],
        ]);
    }

    public function approve(Request $request, Fed $fed)
    {
        $this->authorizeN1($request, $fed);
        $this->assertSubmittable($fed);

        $data = $request->validate([
            'comment' => 'nullable|string|max:2000',
            'n1_avis' => 'required|string|max:2000',
            'n1_signature' => 'required_without:use_saved_signature|nullable|string',
            'use_saved_signature' => 'nullable|boolean',
        ]);

        $signature = $data['n1_signature'] ?? null;
        if (! $signature && $request->boolean('use_saved_signature')) {
            $signature = $request->user()->signature;
        }
        if (! $signature) {
            return redirect()->back()->withErrors(['n1_signature' => 'Signature requise pour valider.']);
        }

        $fed->update([
            'status' => Fed::STATUS_N1_APPROVED,
            'n1_avis' => $data['n1_avis'],
            'n1_comment' => $data['comment'] ?? null,
            'n1_action_at' => now(),
            'n1_signature' => $signature,
            'n1_signed_at' => now(),
            'n1_validated_by' => $request->user()->name,
        ]);

        return redirect()->route('feds.n1.show', $fed)
            ->with('success', 'FED validée.');
    }

    public function reject(Request $request, Fed $fed)
    {
        $this->authorizeN1($request, $fed);
        $this->assertSubmittable($fed);

        $data = $this->validateComment($request);
        $fed->update([
            'status' => Fed::STATUS_N1_REJECTED,
            'n1_comment' => $data['comment'] ?? null,
            'n1_action_at' => now(),
            'n1_validated_by' => $request->user()->name,
        ]);

        return redirect()->route('feds.n1.show', $fed)
            ->with('success', 'FED rejetée par le N+1.');
    }

    public function needsInfo(Request $request, Fed $fed)
    {
        $this->authorizeN1($request, $fed);
        $this->assertSubmittable($fed);

        $data = $this->validateComment($request);
        $fed->update([
            'status' => Fed::STATUS_N1_NEEDS_INFO,
            'n1_comment' => $data['comment'] ?? null,
            'n1_action_at' => now(),
            'n1_validated_by' => $request->user()->name,
        ]);

        return redirect()->route('feds.n1.show', $fed)
            ->with('success', 'Complément demandé au demandeur.');
    }

    public function expertOpinion(Request $request, Fed $fed)
    {
        $this->authorizeN1($request, $fed);
        if ($fed->status !== Fed::STATUS_EXPERT_OPINION_PENDING) {
            abort(403, "Cette FED n'est pas en attente d'avis expert métier.");
        }

        $data = $request->validate([
            'expert_opinion_comment' => 'required|string|max:2000',
            'expert_opinion_offre_id' => 'nullable|integer',
        ], [
            'expert_opinion_comment.required' => 'Votre avis expert est obligatoire.',
        ]);

        $fed->update([
            'status' => Fed::STATUS_EXPERT_OPINION_GIVEN,
            'expert_opinion_comment' => $data['expert_opinion_comment'],
            'expert_opinion_offre_id' => $data['expert_opinion_offre_id'] ?? null,
            'expert_opinion_at' => now(),
        ]);

        return redirect()->route('feds.n1.show', $fed)
            ->with('success', 'Votre avis expert métier a été enregistré.');
    }

    private function authorizeN1(Request $request, Fed $fed): void
    {
        $n1User = $request->user();
        if ($n1User === null) {
            abort(403, 'Utilisateur N+1 introuvable.');
        }

        $requester = $fed->requester;
        if ($requester === null) {
            abort(403, 'Demandeur introuvable.');
        }

        $resolvedN1 = $requester->resolveNPlus1();
        if ($resolvedN1 === null || $resolvedN1->id !== $n1User->id) {
            abort(403, 'Accès non autorisé.');
        }
    }

    private function assertSubmittable(Fed $fed): void
    {
        if ($fed->status !== Fed::STATUS_PENDING_VALIDATION) {
            abort(403, "Cette FED n'est pas en attente de validation.");
        }
    }

    private function validateComment(Request $request): array
    {
        return $request->validate([
            'comment' => 'nullable|string|max:2000',
        ]);
    }
}
