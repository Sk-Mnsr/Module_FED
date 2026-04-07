<?php

namespace App\Http\Controllers;

use App\Models\Agence;
use App\Models\Article;
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
            $budget = $this->resolveBudgetWithLines($selectedDepartmentId, $selectedYear);
        }

        $typologies = TypologieDepense::orderBy('type')->get(['type', 'libelle']);
        $categories = CategorieDepense::with('sousCategories')->orderBy('categorie')->get();
        $articles   = Article::with(['typeDepense', 'sousCategorie.categorie.famille'])->orderBy('description')->get();
        $agences    = Agence::orderBy('nom')->get(['id', 'code', 'nom']);

        $canEdit = $request->user()?->hasAnyRole(['it', 'admin']) ?? false;

        return Inertia::render('budgets/Index', [
            'departments'         => $departments,
            'years'               => $years,
            'selectedDepartmentId' => $selectedDepartmentId,
            'selectedYear'        => $selectedYear,
            'budget'              => $budget,
            'typologies'          => $typologies,
            'categories'          => $categories,
            'articles'            => $articles,
            'agences'             => $agences,
            'canEdit'             => $canEdit,
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
            $budget = $this->resolveBudgetWithLines($selectedDepartmentId, $selectedYear);
        }

        $typologies = TypologieDepense::orderBy('type')->get(['type', 'libelle']);
        $categories = CategorieDepense::with('sousCategories')->orderBy('categorie')->get();
        $articles   = Article::with(['typeDepense', 'sousCategorie.categorie.famille'])->orderBy('description')->get();
        $agences    = Agence::orderBy('nom')->get(['id', 'code', 'nom']);

        return Inertia::render('budgets/Index', [
            'departments'         => $departments,
            'years'               => $years,
            'selectedDepartmentId' => $selectedDepartmentId,
            'selectedYear'        => $selectedYear,
            'budget'              => $budget,
            'typologies'          => $typologies,
            'categories'          => $categories,
            'articles'            => $articles,
            'agences'             => $agences,
            'isN1View'            => true,
            'canEdit'             => false,
        ]);
    }

    public function create()
    {
        $departments = Department::orderBy('name')->get(['id', 'name', 'code']);
        $typologies  = TypologieDepense::orderBy('type')->get(['type', 'libelle']);
        $categories  = CategorieDepense::with('sousCategories')->orderBy('categorie')->get();
        $articles    = Article::with(['typeDepense', 'sousCategorie.categorie.famille'])->orderBy('description')->get();
        $agences     = Agence::orderBy('nom')->get(['id', 'code', 'nom']);

        return Inertia::render('budgets/Create', [
            'departments'         => $departments,
            'typologies'          => $typologies,
            'categories'          => $categories,
            'articles'            => $articles,
            'agences'             => $agences,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateBudget($request);

        DB::beginTransaction();
        try {
            $budget = Budget::create([
                'department_id' => $validated['department_id'],
                'year'          => $validated['year'],
                'total_amount'  => 0,
            ]);

            $total = $this->syncLines($budget, $validated['lines']);
            $budget->update(['total_amount' => $total]);

            DB::commit();
            return redirect()->route('budgets.index')
                ->with('success', 'Budget enregistré.');
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function edit(Budget $budget)
    {
        $departments = Department::orderBy('name')->get(['id', 'name', 'code']);
        $typologies  = TypologieDepense::orderBy('type')->get(['type', 'libelle']);
        $categories  = CategorieDepense::with('sousCategories')->orderBy('categorie')->get();
        $articles    = Article::with(['typeDepense', 'sousCategorie.categorie.famille'])->orderBy('description')->get();
        $agences     = Agence::orderBy('nom')->get(['id', 'code', 'nom']);

        $budget->load([
            'lines' => function ($q) {
                $q->with(['categorieDepense', 'sousCategorieDepense', 'agence', 'entityLines.agence', 'article.sousCategorie.categorie.famille']);
            },
        ]);

        return Inertia::render('budgets/Edit', [
            'budget'              => $budget,
            'departments'         => $departments,
            'typologies'          => $typologies,
            'categories'          => $categories,
            'articles'            => $articles,
            'agences'             => $agences,
        ]);
    }

    public function update(Request $request, Budget $budget)
    {
        $validated = $this->validateBudget($request, $budget->id);

        DB::beginTransaction();
        try {
            $budget->update([
                'department_id' => $validated['department_id'],
                'year'          => $validated['year'],
            ]);

            $total = $this->syncLines($budget, $validated['lines']);
            $budget->update(['total_amount' => $total]);

            DB::commit();
            return redirect()->route('budgets.index')
                ->with('success', 'Budget mis à jour.');
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
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
            'label'                   => 'required|string|max:255',
            'type'                    => 'required|string|exists:typologie_depenses,type',
            'article_id'              => 'nullable|integer|exists:articles,id',
            'categorie_depense_id'    => 'required|integer|exists:categorie_depenses,id',
            'rubrique'                => 'nullable|string|max:255',
            'sous_rubrique'           => 'nullable|string|max:255',
            'montant_estime'          => 'nullable|numeric|min:0',
            'date_souhaitee_execution' => 'nullable|string|max:500',
            'justification'           => 'nullable|string',
            'compte_gl'               => 'nullable|string|max:255',
            'responsable'             => 'nullable|in:IT,Facilities,RH',
        ])->validate();



        $line->update([
            'label'                    => $validated['label'],
            'type'                     => $validated['type'] ?? null,
            'categorie_depense_id'     => $validated['categorie_depense_id'],
            'rubrique'                 => null,
            'sous_rubrique'            => null,
            'montant_estime'           => (float) ($validated['montant_estime'] ?? 0),
            'date_souhaitee_execution' => $validated['date_souhaitee_execution'] ?? null,
            'justification'            => $validated['justification'] ?? null,
            'compte_gl'                => $validated['compte_gl'] ?? null,
            'responsable'              => $validated['responsable'] ?? null,
            'article_id'               => $validated['article_id'] ?? null,
        ]);

        // Regénérer le code si c'est une ligne globale et propager aux entités
        if ($line->is_global) {
            $articleCode = $line->article_id
                ? (Article::find($line->article_id)?->code ?? 'ART')
                : 'ART';
            $catCode = $line->categorieDepense?->code ?? 'CAT';
            $newGlobalCode = BudgetLine::generateGlobalCode($line->type, $articleCode, $catCode);

            $line->update(['code' => $newGlobalCode]);

            // Propager les changements aux lignes entités
            $line->entityLines()->with('agence')->get()->each(function ($entityLine) use ($line, $newGlobalCode) {
                $entityLine->update([
                    'label'                    => $line->label,
                    'type'                     => $line->type,
                    'categorie_depense_id'     => $line->categorie_depense_id,
                    'article_id'               => $line->article_id,
                    'responsable'              => $line->responsable,
                    'compte_gl'                => $line->compte_gl,
                    'date_souhaitee_execution' => $line->date_souhaitee_execution,
                    'justification'            => $line->justification,
                    'code'                     => BudgetLine::generateEntityCode($entityLine->agence?->code ?? 'AG', $newGlobalCode),
                ]);
            });
        }

        $budget = $line->budget;
        $budget->update(['total_amount' => $budget->lines()->where('is_global', true)->sum('montant_estime')]);

        return back()->with('success', 'Ligne budgétaire mise à jour.');
    }

    public function destroyLine(BudgetLine $line)
    {
        $budget = $line->budget;

        // Supprimer aussi les lignes entité associées
        if ($line->is_global) {
            $line->entityLines()->delete();
        }

        $line->delete();
        $budget->update(['total_amount' => $budget->lines()->where('is_global', true)->sum('montant_estime')]);

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
                'Code ligne',
                'Libellé de la dépense',
                'Responsable',
                'Famille',
                'Catégorie',
                'Montant estimé',
                'Montant consommé',
                'Montant stock',
                'Compte GL',
                'Type',
                'Catégorie dépense (old)',
                "Date souhaitée d'exécution",
                'Justifications',
                'Global/Entité',
                'Agence',
            ]);

            $lineNumber = 1;
            $globalLines = $budget->lines->where('is_global', true);
            foreach ($globalLines as $line) {
                fputcsv($handle, [
                    $lineNumber++,
                    $line->code,
                    $line->label,
                    $line->responsable ?? '-',
                    $line->article?->sousCategorie?->categorie?->famille?->nom ?? '-',
                    $line->article?->sousCategorie?->categorie?->nom ?? '-',
                    $line->montant_estime,
                    $line->montant_consomme,
                    $line->montant_stock,
                    $line->compte_gl ?? '-',
                    $line->type,
                    $line->categorieDepense?->categorie ?? '-',
                    $line->date_souhaitee_execution,
                    $line->justification,
                    'Global',
                    '-',
                ]);
                // Lignes entité
                foreach ($line->entityLines ?? [] as $entityLine) {
                    fputcsv($handle, [
                        '',
                        $entityLine->code,
                        $entityLine->label,
                        $entityLine->responsable ?? '-',
                        $entityLine->article?->sousCategorie?->categorie?->famille?->nom ?? '-',
                        $entityLine->article?->sousCategorie?->categorie?->nom ?? '-',
                        $entityLine->montant_estime,
                        $entityLine->montant_consomme,
                        $entityLine->montant_stock,
                        $entityLine->compte_gl ?? '-',
                        $entityLine->type,
                        $entityLine->categorieDepense?->categorie ?? '-',
                        $entityLine->date_souhaitee_execution,
                        $entityLine->justification,
                        'Entité',
                        $entityLine->agence?->nom ?? '-',
                    ]);
                }
            }

            $total = $globalLines->sum('montant_estime');
            fputcsv($handle, ['TOTAL BUDGET', '', '', '', '', '', $total, '', '', '', '', '', '', '', '', '']);
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
            'budget'         => $budget,
            'departmentName' => $budget->department?->name ?? 'Département',
            'year'           => $budget->year,
            'totalEstime'    => $budget->lines->where('is_global', true)->sum('montant_estime'),
        ])->setPaper('a4', 'landscape');

        $filename = 'budget_' . Str::slug($budget->department?->name ?? 'departement') . '_' . $budget->year . '.pdf';

        return $pdf->download($filename);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // Méthodes privées
    // ────────────────────────────────────────────────────────────────────────────

    private function resolveBudgetWithLines(int $departmentId, int $year): ?object
    {
        $budgets = Budget::with([
            'lines' => function ($q) {
                $q->with(['categorieDepense', 'sousCategorieDepense', 'agence', 'entityLines.agence', 'article.sousCategorie.categorie.famille'])
                  ->orderBy('is_global', 'desc')
                  ->orderBy('id');
            },
            'department',
        ])
            ->where('department_id', $departmentId)
            ->where('year', $year)
            ->orderBy('id')
            ->get();

        if ($budgets->isEmpty()) {
            return null;
        }

        $first = $budgets->first();
        return (object) [
            'id'            => $first->id,
            'department_id' => $first->department_id,
            'year'          => $first->year,
            'lines'         => $budgets->pluck('lines')->flatten()->values()->all(),
        ];
    }

    private function validateBudget(Request $request, ?int $budgetId = null): array
    {
        $validator = Validator::make($request->all(), [
            'department_id'                        => 'required|integer|exists:departments,id',
            'year'                                 => ['required', 'integer', 'min:2000', 'max:2100'],
            'lines'                                => 'required|array|min:1',
            'lines.*.label'                        => 'required|string|max:255',
            'lines.*.type'                         => 'required|string|exists:typologie_depenses,type',
            'lines.*.article_id'                   => 'nullable|integer|exists:articles,id',
            'lines.*.categorie_depense_id'         => 'required|integer|exists:categorie_depenses,id',
            'lines.*.rubrique'                     => 'nullable|string|max:255',
            'lines.*.sous_rubrique'                => 'nullable|string|max:255',
            'lines.*.montant_estime'               => 'nullable|numeric|min:0',
            'lines.*.date_souhaitee_execution'     => 'nullable|string|max:500',
            'lines.*.justification'                => 'nullable|string',
            'lines.*.compte_gl'                    => 'nullable|string|max:255',
            'lines.*.responsable'                  => 'nullable|in:IT,Facilities,RH',
        ]);

        return $validator->validate();
    }

    private function syncLines(Budget $budget, array $lines): float
    {
        // Supprimer les anciennes lignes globales et leurs entités
        BudgetLine::where('budget_id', $budget->id)->delete();
        $budget->load('department');

        $agences = Agence::orderBy('nom')->get();
        $total   = 0;

        foreach ($lines as $line) {
            $montantEstime   = (float) ($line['montant_estime'] ?? 0);
            $total          += $montantEstime;

            $type         = $line['type'] ?? null;
            $articleId    = isset($line['article_id']) ? (int) $line['article_id'] : null;
            $articleCode  = $articleId ? (Article::find($articleId)?->code ?? 'ART') : 'ART';
            $categorieId  = isset($line['categorie_depense_id']) ? (int) $line['categorie_depense_id'] : null;
            $categorie    = CategorieDepense::find($categorieId);
            $catCode      = $categorie?->code ?? 'CAT';

            $globalCode = BudgetLine::generateGlobalCode($type, $articleCode, $catCode);

            // Créer la ligne globale
            $globalLine = BudgetLine::create([
                'budget_id'                => $budget->id,
                'code'                     => $globalCode,
                'label'                    => $line['label'],
                'type'                     => $type,
                'categorie_depense_id'     => $categorieId,
                'montant_estime'           => $montantEstime,
                'date_souhaitee_execution' => $line['date_souhaitee_execution'] ?? null,
                'justification'            => $line['justification'] ?? null,
                'compte_gl'                => $line['compte_gl'] ?? null,
                'responsable'              => $line['responsable'] ?? null,
                'article_id'               => $articleId,
                'is_global'                => true,
                'global_line_id'           => null,
                'agence_id'                => null,
            ]);

            // Créer automatiquement une ligne entité pour chaque agence
            foreach ($agences as $agence) {
                BudgetLine::create([
                    'budget_id'                => $budget->id,
                    'code'                     => BudgetLine::generateEntityCode($agence->code, $globalCode),
                    'label'                    => $line['label'],
                    'type'                     => $type,
                    'categorie_depense_id'     => $categorieId,
                    'montant_estime'           => 0,
                    'date_souhaitee_execution' => $line['date_souhaitee_execution'] ?? null,
                    'justification'            => $line['justification'] ?? null,
                    'compte_gl'                => $line['compte_gl'] ?? null,
                    'responsable'              => $line['responsable'] ?? null,
                    'article_id'               => $articleId,
                    'is_global'                => false,
                    'global_line_id'           => $globalLine->id,
                    'agence_id'                => $agence->id,
                ]);
            }
        }

        return $total;
    }

    private function resolveBudgetForExport(Request $request): ?Budget
    {
        $departmentId = $request->integer('department_id');
        $year         = $request->integer('year');

        if (!$departmentId || !$year) {
            return null;
        }

        return Budget::with([
            'lines' => function ($q) {
                $q->with(['categorieDepense', 'sousCategorieDepense', 'agence', 'entityLines.agence', 'article.sousCategorie.categorie.famille'])
                  ->orderBy('is_global', 'desc')->orderBy('id');
            },
            'department',
        ])
            ->where('department_id', $departmentId)
            ->where('year', $year)
            ->first();
    }
}
