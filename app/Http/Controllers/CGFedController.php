<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\BudgetLineHistory;
use App\Models\Fed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CGFedController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);
        $status = $request->get('status');

        $feds = Fed::with(['requester'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->whereIn('status', [Fed::STATUS_FACILITIES_APPROVED, Fed::STATUS_CG_TREATED])
            ->orderByDesc('facilities_action_at')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return Inertia::render('feds/CG/CGIndex', [
            'feds'           => $feds,
            'selectedStatus' => $status,
        ]);
    }

    public function show(Request $request, Fed $fed)
    {
        $this->assertViewable($fed);

        $fed->load([
            'items',
            'attachments',
            'requester',
            'items.budgetLine.budget.department',
            'items.budgetLine.categorieDepense',
            'items.budgetLine.sousCategorieDepense',
            'fournisseurOffres.attachments',
            'budgetLineHistories.user',
            'budgetLineHistories.fromLine',
            'budgetLineHistories.toLine',
        ]);

        // Charger toutes les lignes budgétaires pour permettre un changement / reclassement flexible
        $budgetLines = BudgetLine::orderBy('code')
            ->get(['id', 'code', 'label', 'montant_estime', 'is_reclassified']);

        return Inertia::render('feds/CG/CGShow', [
            'fed'         => $fed,
            'budgetLines' => $budgetLines,
        ]);
    }

    public function showReclasser(Request $request, Fed $fed)
    {
        $this->assertViewable($fed);

        $fed->load([
            'items',
            'requester',
            'items.budgetLine',
            'budgetLineHistories.user',
            'budgetLineHistories.fromLine',
            'budgetLineHistories.toLine',
        ]);

        $budgetLines = BudgetLine::orderBy('code')
            ->get(['id', 'code', 'label', 'montant_estime', 'is_reclassified']);

        return Inertia::render('feds/CG/CGReclasser', [
            'fed'         => $fed,
            'budgetLines' => $budgetLines,
        ]);
    }

    public function treat(Request $request, Fed $fed)
    {
        $this->assertValidatable($fed);

        $data = $request->validate([
            'cg_budget_status'      => 'required|in:in_budget,out_of_budget',
            'comment'               => 'nullable|string|max:2000',
            'item_budget_lines'     => 'nullable|array',
            'item_budget_lines.*'   => 'nullable|integer|exists:budget_lines,id',
        ], [
            'cg_budget_status.required' => 'Veuillez indiquer si la demande est dans le budget ou non.',
            'cg_budget_status.in'       => 'Le statut budgétaire est invalide.',
            'item_budget_lines.array'   => 'Le format des lignes budgétaires par article est invalide.',
        ]);

        DB::beginTransaction();

        // Si des lignes budgétaires sont fournies pour chaque article
        if (!empty($data['item_budget_lines'])) {
            // S'assurer de charger les items si ce n'est pas déjà fait
            $fed->loadMissing('items');
            
            foreach ($fed->items as $item) {
                $newLineId = $data['item_budget_lines'][$item->id] ?? null;
                if ($newLineId && $newLineId != $item->budget_line_id) {
                    $oldLineId = $item->budget_line_id;
                    $item->update(['budget_line_id' => $newLineId]);
                    
                    // Historique du changement de ligne par article
                    BudgetLineHistory::create([
                        'fed_id'       => $fed->id,
                        'user_id'      => $request->user()->id,
                        'from_line_id' => $oldLineId,
                        'to_line_id'   => $newLineId,
                        'action'       => 'change_line',
                        'note'         => "Changement de ligne budgétaire pour l'article '{$item->label}' lors de la vérification CG.",
                    ]);
                }
            }
        }

        $fed->update([
            'status'           => Fed::STATUS_CG_TREATED,
            'cg_budget_status' => $data['cg_budget_status'],
            'cg_comment'       => $data['comment'] ?? null,
            'cg_action_at'     => now(),
            'cg_validated_by'  => $request->user()->name,
        ]);

        DB::commit();

        return redirect()->route('feds.cg.show', $fed)
            ->with('success', 'Statut budgétaire enregistré.');
    }

    /**
     * Reclassement : transfère un montant d'une ligne budgétaire à une autre.
     */
    public function reclassifyTransfer(Request $request, Fed $fed)
    {
        $this->assertViewable($fed);

        $data = $request->validate([
            'from_line_id'      => 'required|integer|exists:budget_lines,id',
            'to_line_id'        => 'required|integer|exists:budget_lines,id|different:from_line_id',
            'montant_transfere' => 'required|numeric|min:0.01',
            'note'              => 'nullable|string|max:1000',
        ], [
            'from_line_id.required'      => 'Veuillez sélectionner la ligne source.',
            'to_line_id.required'        => 'Veuillez sélectionner la ligne cible.',
            'to_line_id.different'       => 'Les lignes source et cible doivent être différentes.',
            'montant_transfere.required' => 'Veuillez saisir le montant à transférer.',
            'montant_transfere.min'      => 'Le montant doit être supérieur à 0.',
        ]);

        $fromLine = BudgetLine::findOrFail($data['from_line_id']);
        $toLine   = BudgetLine::findOrFail($data['to_line_id']);
        $montant  = (float) $data['montant_transfere'];

        DB::beginTransaction();

        $fromBefore = (float) $fromLine->montant_estime;
        $toBefore   = (float) $toLine->montant_estime;

        // On n'effectue plus le calcul ici, on trace simplement la DEMANDE
        // Et on change le statut de la FED pour l'envoyer au DAF

        // Historique en "pending"
        BudgetLineHistory::create([
            'fed_id'              => $fed->id,
            'user_id'             => $request->user()->id,
            'from_line_id'        => $fromLine->id,
            'to_line_id'          => $toLine->id,
            'action'              => 'transfer_amount',
            'montant_transfere'   => $montant,
            'from_montant_before' => $fromBefore,
            'from_montant_after'  => null, // Pas encore d'impact
            'to_montant_before'   => $toBefore,
            'to_montant_after'    => null, // Pas encore d'impact
            'note'                => $data['note'] ?? null,
            'status'              => 'pending',
        ]);

        $fed->update([
            'status' => Fed::STATUS_WAITING_DAF_RECLASS_APPROVAL,
        ]);

        DB::commit();

        return redirect()->route('feds.cg.index')->with('success', 'Demande de reclassement envoyée au DAF pour validation.');
    }

    /**
     * Changer la ligne budgétaire associée à la FED (sans traiter).
     */
    public function changeBudgetLine(Request $request, Fed $fed)
    {
        $this->assertViewable($fed);

        $data = $request->validate([
            'budget_line_id' => 'required|integer|exists:budget_lines,id',
            'note'           => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();

        $oldLine = null;
        $firstItem = $fed->items->first(function ($item) {
            return $item->budget_line_id !== null;
        });
        if ($firstItem) {
            $oldLine = BudgetLine::find($firstItem->budget_line_id);
        }
        $newLine = BudgetLine::findOrFail($data['budget_line_id']);

        // Appliquer à tous les items
        foreach ($fed->items as $item) {
            $item->update(['budget_line_id' => $newLine->id]);
        }

        BudgetLineHistory::create([
            'fed_id'       => $fed->id,
            'user_id'      => $request->user()->id,
            'from_line_id' => $oldLine?->id,
            'to_line_id'   => $newLine->id,
            'action'       => 'change_line',
            'note'         => $data['note'] ?? 'Changement de ligne budgétaire par le CG.',
        ]);

        DB::commit();

        return back()->with('success', 'Ligne budgétaire mise à jour.');
    }

    private function assertViewable(Fed $fed): void
    {
        if (!in_array($fed->status, [
            Fed::STATUS_FACILITIES_APPROVED,
            Fed::STATUS_CG_TREATED,
        ], true)) {
            abort(403, "Cette FED n'est pas accessible au Contrôle de Gestion.");
        }
    }

    private function assertValidatable(Fed $fed): void
    {
        if ($fed->status !== Fed::STATUS_FACILITIES_APPROVED) {
            abort(403, "Cette FED n'est pas accessible au Contrôle de Gestion.");
        }
    }
}