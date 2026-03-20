<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\CategorieDepense;
use App\Models\Department;
use App\Models\RubriqueDepense;
use App\Models\Profil;
use App\Models\SousCategorieDepense;
use App\Models\TypologieDepense;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;

class BudgetController extends Controller
{
    public function index(Request $request)
    {
        $departments = Department::orderBy('name')->get(['id', 'name']);
        $years = Budget::query()
            ->select('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->values();

        $selectedDepartmentId = $request->integer('department_id');
        $selectedYear = $request->integer('year');

        $budget = null;
        if ($selectedDepartmentId && $selectedYear) {
            $budgets = Budget::with(['lines.categorieDepense', 'lines.sousCategorieDepense', 'department'])
                ->where('department_id', $selectedDepartmentId)
                ->where('year', $selectedYear)
                ->orderBy('id')
                ->get();
            if ($budgets->isNotEmpty()) {
                $first = $budgets->first();
                $budget = (object) [
                    'id' => $first->id,
                    'department_id' => $first->department_id,
                    'year' => $first->year,
                    'lines' => $budgets->pluck('lines')->flatten()->values()->all(),
                ];
            }
        }

        $typologies = TypologieDepense::orderBy('type')->get(['type', 'libelle']);
        $categories = CategorieDepense::with('sousCategories')->orderBy('categorie')->get();
        $rubriqueSuggestions = RubriqueDepense::orderBy('libelle')->pluck('libelle')->toArray();

        $canEdit = $request->user()?->hasAnyRole(['it', 'admin']) ?? false;

        return Inertia::render('budgets/Index', [
            'departments' => $departments,
            'years' => $years,
            'selectedDepartmentId' => $selectedDepartmentId,
            'selectedYear' => $selectedYear,
            'budget' => $budget,
            'typologies' => $typologies,
            'categories' => $categories,
            'rubriqueSuggestions' => $rubriqueSuggestions,
            'canEdit' => $canEdit,
        ]);
    }

    public function indexForN1(Request $request)
    {
        $profil = $request->user()?->profil;
        if (!$profil) {
            abort(403, 'Profil N+1 introuvable.');
        }

        $managedDepartmentIds = Department::where('manager_profile_id', $profil->id)->pluck('id')->toArray();
        $subordinateDepartmentIds = Profil::where('n_plus_1_id', $profil->id)
            ->whereNotNull('department_id')
            ->pluck('department_id')
            ->toArray();

        $departmentIds = array_values(array_unique(array_filter(array_merge(
            [$profil->department_id],
            $managedDepartmentIds,
            $subordinateDepartmentIds
        ))));

        $departments = Department::whereIn('id', $departmentIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        $years = Budget::query()
            ->whereIn('department_id', $departmentIds)
            ->select('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->values();

        $selectedDepartmentId = $request->integer('department_id');
        if ($selectedDepartmentId && !in_array($selectedDepartmentId, $departmentIds, true)) {
            $selectedDepartmentId = null;
        }
        $selectedYear = $request->integer('year');

        $budget = null;
        if ($selectedDepartmentId && $selectedYear) {
            $budgets = Budget::with(['lines.categorieDepense', 'lines.sousCategorieDepense', 'department'])
                ->where('department_id', $selectedDepartmentId)
                ->where('year', $selectedYear)
                ->orderBy('id')
                ->get();
            if ($budgets->isNotEmpty()) {
                $first = $budgets->first();
                $budget = (object) [
                    'id' => $first->id,
                    'department_id' => $first->department_id,
                    'year' => $first->year,
                    'lines' => $budgets->pluck('lines')->flatten()->values()->all(),
                ];
            }
        }

        $typologies = TypologieDepense::orderBy('type')->get(['type', 'libelle']);
        $categories = CategorieDepense::with('sousCategories')->orderBy('categorie')->get();
        $rubriqueSuggestions = RubriqueDepense::orderBy('libelle')->pluck('libelle')->toArray();

        return Inertia::render('budgets/Index', [
            'departments' => $departments,
            'years' => $years,
            'selectedDepartmentId' => $selectedDepartmentId,
            'selectedYear' => $selectedYear,
            'budget' => $budget,
            'typologies' => $typologies,
            'categories' => $categories,
            'rubriqueSuggestions' => $rubriqueSuggestions,
            'isN1View' => true,
            'canEdit' => false,
        ]);
    }

    public function create()
    {
        $departments = Department::orderBy('name')->get(['id', 'name', 'code']);
        $typologies = TypologieDepense::orderBy('type')->get(['type', 'libelle']);
        $categories = CategorieDepense::with('sousCategories')->orderBy('categorie')->get();
        $rubriqueSuggestions = RubriqueDepense::orderBy('libelle')->pluck('libelle')->toArray();

        return Inertia::render('budgets/Create', [
            'departments' => $departments,
            'typologies' => $typologies,
            'categories' => $categories,
            'rubriqueSuggestions' => $rubriqueSuggestions,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateBudget($request);

        DB::beginTransaction();

        $budget = Budget::create([
            'department_id' => $validated['department_id'],
            'year' => $validated['year'],
            'total_amount' => 0,
        ]);

        $total = $this->syncLines($budget, $validated['lines']);
        $budget->update(['total_amount' => $total]);

        DB::commit();

        return redirect()->route('budgets.index')
            ->with('success', 'Budget enregistré.');
    }

    public function edit(Budget $budget)
    {
        $departments = Department::orderBy('name')->get(['id', 'name', 'code']);
        $typologies = TypologieDepense::orderBy('type')->get(['type', 'libelle']);
        $categories = CategorieDepense::with('sousCategories')->orderBy('categorie')->get();
        $rubriqueSuggestions = RubriqueDepense::orderBy('libelle')->pluck('libelle')->toArray();

        return Inertia::render('budgets/Edit', [
            'budget' => $budget->load(['lines.categorieDepense', 'lines.sousCategorieDepense']),
            'departments' => $departments,
            'typologies' => $typologies,
            'categories' => $categories,
            'rubriqueSuggestions' => $rubriqueSuggestions,
        ]);
    }

    public function update(Request $request, Budget $budget)
    {
        $validated = $this->validateBudget($request, $budget->id);

        DB::beginTransaction();

        $budget->update([
            'department_id' => $validated['department_id'],
            'year' => $validated['year'],
        ]);

        $total = $this->syncLines($budget, $validated['lines']);
        $budget->update(['total_amount' => $total]);

        DB::commit();

        return redirect()->route('budgets.index')
            ->with('success', 'Budget mis à jour.');
    }

    public function destroy(Budget $budget)
    {
        $budget->delete();

        return redirect()->route('budgets.index')
            ->with('success', 'Budget supprimé.');
    }

    public function updateLine(Request $request, BudgetLine $line)
    {
        $validated = Validator::make($request->all(), [
            'label' => 'required|string|max:255',
            'type' => 'required|string|exists:typologie_depenses,type',
            'categorie_depense_id' => 'required|integer|exists:categorie_depenses,id',
            'sous_categorie_depense_id' => 'nullable|integer|exists:sous_categorie_depenses,id',
            'sous_categorie' => 'required|string|max:255',
            'rubrique' => 'nullable|string|max:255',
            'sous_rubrique' => 'nullable|string|max:255',
            'montant_estime' => 'nullable|numeric|min:0',
            'date_souhaitee_execution' => 'nullable|string|max:500',
            'justification' => 'nullable|string',
            'compte_gl' => 'nullable|string|max:255',
        ])->validate();

        $rubrique = trim($validated['rubrique'] ?? '') ?: null;
        if ($rubrique) {
            RubriqueDepense::addIfNew($rubrique);
        }

        $sousCategorieId = $validated['sous_categorie_depense_id'] ?? null;
        if (!$sousCategorieId && !empty(trim($validated['sous_categorie'] ?? ''))) {
            $sousCategorieId = SousCategorieDepense::addIfNew($validated['categorie_depense_id'], $validated['sous_categorie']);
        }

        $line->update([
            'label' => $validated['label'],
            'type' => $validated['type'] ?? null,
            'categorie_depense_id' => $validated['categorie_depense_id'],
            'sous_categorie_depense_id' => $sousCategorieId,
            'rubrique' => $rubrique,
            'sous_rubrique' => $validated['sous_rubrique'] ?? null,
            'montant_estime' => (float) ($validated['montant_estime'] ?? 0),
            'date_souhaitee_execution' => $validated['date_souhaitee_execution'] ?? null,
            'justification' => $validated['justification'] ?? null,
            'compte_gl' => $validated['compte_gl'] ?? null,
        ]);

        $line->update([
            'code' => BudgetLine::generateCode($line->budget, $line->type, $line->sous_categorie_depense_id, $line->id),
        ]);

        $budget = $line->budget;
        $budget->update(['total_amount' => $budget->lines()->sum('montant_estime')]);

        return back()->with('success', 'Ligne budgétaire mise à jour.');
    }

    public function destroyLine(BudgetLine $line)
    {
        $budget = $line->budget;
        $line->delete();
        $budget->update(['total_amount' => $budget->lines()->sum('montant_estime')]);

        return back()->with('success', 'Ligne budgétaire supprimée.');
    }

    public function exportExcel(Request $request)
    {
        $budget = $this->resolveBudgetForExport($request);
        if (!$budget) {
            abort(404, 'Budget introuvable.');
        }

        $departmentName = $budget->department?->name ?? 'departement';
        $filename = 'budget_' . Str::slug($departmentName) . '_' . $budget->year . '.csv';

        return response()->streamDownload(function () use ($budget) {
            echo "\xEF\xBB\xBF";
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'N°',
                'Code ligne budgétaire',
                'Libellé de la dépense',
                'Type',
                'Catégorie dépense',
                'Sous catégorie',
                'Rubrique dépenses',
                'Sous rubrique',
                'Montant estimé',
                'Montant consommé',
                'Montant stock',
                "Date souhaitée d'exécution",
                'Justifications',
                'Compte GL',
            ]);

            $lineNumber = 1;
            foreach ($budget->lines as $line) {
                fputcsv($handle, [
                    $lineNumber++,
                    $line->code,
                    $line->label,
                    $line->type,
                    $line->categorieDepense?->categorie ?? '-',
                    $line->sousCategorieDepense?->sous_categorie ?? '-',
                    $line->rubrique ?? '-',
                    $line->sous_rubrique ?? '-',
                    $line->montant_estime,
                    $line->montant_consomme,
                    $line->montant_stock,
                    $line->date_souhaitee_execution,
                    $line->justification,
                    $line->compte_gl ?? '-',
                ]);
            }

            fputcsv($handle, [
                'TOTAL BUDGET',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                $budget->lines->sum('montant_estime'),
                $budget->lines->sum('montant_consomme'),
                $budget->lines->sum('montant_stock'),
                '',
                '',
                '',
            ]);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportPdf(Request $request)
    {
        $budget = $this->resolveBudgetForExport($request);
        if (!$budget) {
            abort(404, 'Budget introuvable.');
        }

        $pdf = Pdf::loadView('budgets.report', [
            'budget' => $budget,
            'departmentName' => $budget->department?->name ?? 'Département',
            'year' => $budget->year,
            'totalEstime' => $budget->lines->sum('montant_estime'),
        ])->setPaper('a4', 'landscape');

        $filename = 'budget_' . Str::slug($budget->department?->name ?? 'departement') . '_' . $budget->year . '.pdf';

        return $pdf->download($filename);
    }

    private function validateBudget(Request $request, ?int $budgetId = null): array
    {
        $validator = Validator::make($request->all(), [
            'department_id' => 'required|integer|exists:departments,id',
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'lines' => 'required|array|min:1',
            'lines.*.label' => 'required|string|max:255',
            'lines.*.type' => 'required|string|exists:typologie_depenses,type',
            'lines.*.categorie_depense_id' => 'required|integer|exists:categorie_depenses,id',
            'lines.*.sous_categorie_depense_id' => 'nullable|integer|exists:sous_categorie_depenses,id',
            'lines.*.sous_categorie' => 'required|string|max:255',
            'lines.*.rubrique' => 'nullable|string|max:255',
            'lines.*.sous_rubrique' => 'nullable|string|max:255',
            'lines.*.montant_estime' => 'nullable|numeric|min:0',
            'lines.*.date_souhaitee_execution' => 'nullable|string|max:500',
            'lines.*.justification' => 'nullable|string',
            'lines.*.compte_gl' => 'nullable|string|max:255',
        ]);

        return $validator->validate();
    }

    private function syncLines(Budget $budget, array $lines): float
    {
        BudgetLine::where('budget_id', $budget->id)->delete();
        $budget->load('department');

        $total = 0;

        foreach ($lines as $line) {
            $montantEstime = (float) ($line['montant_estime'] ?? 0);
            $total += $montantEstime;

            $type = $line['type'] ?? null;
            $categorieId = isset($line['categorie_depense_id']) ? (int) $line['categorie_depense_id'] : null;
            $sousCategorieId = isset($line['sous_categorie_depense_id']) && $line['sous_categorie_depense_id']
                ? (int) $line['sous_categorie_depense_id']
                : null;
            if (!$sousCategorieId && $categorieId && !empty(trim($line['sous_categorie'] ?? ''))) {
                $sousCategorieId = SousCategorieDepense::addIfNew($categorieId, $line['sous_categorie']);
            }

            $rubrique = trim($line['rubrique'] ?? '') ?: null;
            if ($rubrique) {
                RubriqueDepense::addIfNew($rubrique);
            }

            $budgetLine = BudgetLine::create([
                'budget_id' => $budget->id,
                'code' => BudgetLine::generateCode($budget, $type, $sousCategorieId),
                'label' => $line['label'],
                'type' => $type,
                'categorie_depense_id' => $line['categorie_depense_id'] ?? null,
                'sous_categorie_depense_id' => $sousCategorieId,
                'rubrique' => $rubrique,
                'sous_rubrique' => $line['sous_rubrique'] ?? null,
                'montant_estime' => $montantEstime,
                'date_souhaitee_execution' => $line['date_souhaitee_execution'] ?? null,
                'justification' => $line['justification'] ?? null,
                'compte_gl' => $line['compte_gl'] ?? null,
            ]);
        }

        return $total;
    }

    private function resolveBudgetForExport(Request $request): ?Budget
    {
        $departmentId = $request->integer('department_id');
        $year = $request->integer('year');

        if (!$departmentId || !$year) {
            return null;
        }

        return Budget::with(['lines.categorieDepense', 'lines.sousCategorieDepense', 'department'])
            ->where('department_id', $departmentId)
            ->where('year', $year)
            ->first();
    }
}
