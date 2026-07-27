<?php

namespace App\Http\Controllers;

use App\Models\Agence;
use App\Models\Article;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\CategorieDepense;
use App\Models\Department;
use App\Models\RubriqueDepense;
use App\Models\User;
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
        $years = $this->availableYears();

        $selectedDepartmentId = $request->integer('department_id') ?: null;
        $selectedYear = $request->integer('year') ?: null;

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
        $user = $request->user();
        if ($user === null) {
            abort(403, 'Utilisateur N+1 introuvable.');
        }

        $managedDepartmentIds = Department::where('manager_user_id', $user->id)->pluck('id')->toArray();
        $subordinateDepartmentIds = User::where('n_plus_1_user_id', $user->id)
            ->whereNotNull('department_id')
            ->pluck('department_id')
            ->toArray();

        $departmentIds = array_values(array_unique(array_filter(array_merge(
            [$user->department_id],
            $managedDepartmentIds,
            $subordinateDepartmentIds
        ))));

        $departments = Department::whereIn('id', $departmentIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        $years = $this->availableYears();

        $selectedDepartmentId = $request->integer('department_id') ?: null;
        if ($selectedDepartmentId && !in_array($selectedDepartmentId, $departmentIds, true)) {
            $selectedDepartmentId = null;
        }
        $selectedYear = $request->integer('year') ?: null;

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
        $departmentId = $request->integer('department_id');
        $year = $request->integer('year');

        if (! $departmentId || ! $year) {
            abort(422, 'Département et année requis.');
        }

        $budget = $this->resolveBudgetForExport($request);
        $departmentName = $budget?->department?->name
            ?? Department::query()->whereKey($departmentId)->value('name')
            ?? 'departement';
        $filename = 'budget_'.Str::slug($departmentName).'_'.$year.'.csv';

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

            if ($budget === null) {
                fclose($handle);

                return;
            }

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

    /**
     * Import CSV (même format que l’export Excel) — upsert par code ligne.
     */
    public function import(Request $request)
    {
        $validated = $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ], [
            'department_id.required' => 'Sélectionnez un département.',
            'year.required' => 'Sélectionnez une année.',
            'file.required' => 'Choisissez un fichier CSV à importer.',
            'file.mimes' => 'Le fichier doit être un CSV.',
        ]);

        $budget = Budget::query()->firstOrCreate(
            [
                'department_id' => (int) $validated['department_id'],
                'year' => (int) $validated['year'],
            ],
            ['total_amount' => 0],
        );

        $path = $request->file('file')->getRealPath();
        $handle = fopen($path ?: '', 'r');
        if ($handle === false) {
            return back()->with('error', 'Impossible de lire le fichier importé.');
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);

            return back()->with('error', 'Le fichier CSV est vide.');
        }

        // Retirer le BOM éventuel
        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine) ?? $firstLine;
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        $headers = str_getcsv($firstLine, $delimiter);
        $headers = array_map(fn ($h) => mb_strtolower(trim((string) $h)), $headers);

        $col = function (string ...$names) use ($headers): ?int {
            foreach ($names as $name) {
                $idx = array_search(mb_strtolower($name), $headers, true);
                if ($idx !== false) {
                    return (int) $idx;
                }
            }

            return null;
        };

        $idxCode = $col('code ligne', 'code');
        $idxLabel = $col('libellé de la dépense', 'libelle', 'libellé');
        $idxResponsable = $col('responsable');
        $idxMontant = $col('montant estimé', 'montant estime');
        $idxConsomme = $col('montant consommé', 'montant consomme');
        $idxStock = $col('montant stock');
        $idxCompte = $col('compte gl');
        $idxType = $col('type');
        $idxCategorieOld = $col('catégorie dépense (old)', 'categorie depense (old)', 'catégorie dépense');
        $idxDate = $col("date souhaitée d'exécution", 'date souhaitee d\'execution', 'date souhaitée');
        $idxJustif = $col('justifications', 'justification');
        $idxScope = $col('global/entité', 'global/entite', 'global/entité');
        $idxAgence = $col('agence');

        if ($idxCode === null || $idxLabel === null) {
            fclose($handle);

            return back()->with('error', 'Colonnes obligatoires manquantes : Code ligne, Libellé de la dépense.');
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $rowNum = 1;
        $lastGlobal = null;

        $validTypes = TypologieDepense::query()->pluck('type')->map(fn ($t) => (string) $t)->all();
        $categoriesByName = CategorieDepense::query()->get()->keyBy(fn ($c) => mb_strtolower(trim((string) $c->categorie)));
        $agencesByName = Agence::query()->get()->keyBy(fn ($a) => mb_strtolower(trim((string) $a->nom)));
        $agencesByCode = Agence::query()->get()->keyBy(fn ($a) => mb_strtoupper(trim((string) $a->code)));

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rowNum++;
                if ($row === [null] || $row === false || count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue;
                }

                $get = function (?int $i) use ($row): string {
                    if ($i === null || ! array_key_exists($i, $row)) {
                        return '';
                    }

                    return trim((string) $row[$i]);
                };

                $code = $get($idxCode);
                $label = $get($idxLabel);
                $firstCell = $get(0);

                if (str_starts_with(mb_strtoupper($firstCell), 'TOTAL') || str_starts_with(mb_strtoupper($code), 'TOTAL')) {
                    continue;
                }

                if ($code === '' || $label === '') {
                    $skipped++;
                    continue;
                }

                $scopeRaw = mb_strtolower($get($idxScope));
                $isEntity = str_contains($scopeRaw, 'entité') || str_contains($scopeRaw, 'entite');

                $type = $get($idxType);
                if ($type === '' && $lastGlobal) {
                    $type = (string) ($lastGlobal->type ?? '');
                }
                if ($type !== '' && ! in_array($type, $validTypes, true)) {
                    $errors[] = "Ligne {$rowNum} : type « {$type} » inconnu.";
                    $skipped++;
                    continue;
                }

                $responsable = $get($idxResponsable);
                if ($responsable === '-' || $responsable === '') {
                    $responsable = null;
                } elseif (! in_array($responsable, ['IT', 'Facilities', 'RH'], true)) {
                    $responsable = null;
                }

                $montantEstime = $this->parseImportAmount($get($idxMontant));
                $montantConsomme = $this->parseImportAmount($get($idxConsomme));
                $montantStock = $this->parseImportAmount($get($idxStock));
                $compteGl = $get($idxCompte);
                if ($compteGl === '-') {
                    $compteGl = null;
                }
                $dateExec = $get($idxDate);
                $justification = $get($idxJustif);
                $catName = $get($idxCategorieOld);
                $categorieId = null;
                if ($catName !== '' && $catName !== '-') {
                    $categorieId = $categoriesByName->get(mb_strtolower($catName))?->id;
                }

                if ($isEntity) {
                    $agenceName = $get($idxAgence);
                    $agence = null;
                    if ($agenceName !== '' && $agenceName !== '-') {
                        $agence = $agencesByName->get(mb_strtolower($agenceName));
                    }
                    if ($agence === null && str_contains($code, '_')) {
                        $prefix = strtoupper(explode('_', $code, 2)[0] ?? '');
                        $agence = $agencesByCode->get($prefix);
                    }

                    $parent = $lastGlobal;
                    if (str_contains($code, '_')) {
                        $globalCode = explode('_', $code, 2)[1] ?? '';
                        if ($globalCode !== '') {
                            $found = BudgetLine::query()
                                ->where('budget_id', $budget->id)
                                ->where('is_global', true)
                                ->where('code', $globalCode)
                                ->first();
                            if ($found) {
                                $parent = $found;
                            }
                        }
                    }

                    if ($parent === null) {
                        $errors[] = "Ligne {$rowNum} : ligne entité « {$code} » sans ligne globale parente.";
                        $skipped++;
                        continue;
                    }

                    $existing = BudgetLine::query()
                        ->where('budget_id', $budget->id)
                        ->where('code', $code)
                        ->first();

                    $payload = [
                        'label' => $label,
                        'type' => $type !== '' ? $type : $parent->type,
                        'categorie_depense_id' => $categorieId ?? $parent->categorie_depense_id,
                        'montant_estime' => $montantEstime,
                        'montant_consomme' => $montantConsomme,
                        'montant_stock' => $montantStock,
                        'compte_gl' => $compteGl ?: $parent->compte_gl,
                        'date_souhaitee_execution' => $dateExec !== '' ? $dateExec : $parent->date_souhaitee_execution,
                        'justification' => $justification !== '' ? $justification : $parent->justification,
                        'responsable' => $responsable ?? $parent->responsable,
                        'is_global' => false,
                        'global_line_id' => $parent->id,
                        'agence_id' => $agence?->id,
                    ];

                    if ($existing) {
                        $existing->update($payload);
                        $updated++;
                    } else {
                        BudgetLine::create(array_merge($payload, [
                            'budget_id' => $budget->id,
                            'code' => $code,
                            'article_id' => $parent->article_id,
                        ]));
                        $created++;
                    }

                    continue;
                }

                // Ligne globale
                if ($type === '') {
                    $errors[] = "Ligne {$rowNum} : type obligatoire pour la ligne globale « {$code} ».";
                    $skipped++;
                    continue;
                }

                $existing = BudgetLine::query()
                    ->where('budget_id', $budget->id)
                    ->where('is_global', true)
                    ->where('code', $code)
                    ->first();

                if (! $existing && $categorieId === null) {
                    $errors[] = "Ligne {$rowNum} : catégorie dépense introuvable pour créer « {$code} ».";
                    $skipped++;
                    continue;
                }

                $payload = [
                    'label' => $label,
                    'type' => $type,
                    'montant_estime' => $montantEstime,
                    'montant_consomme' => $montantConsomme,
                    'montant_stock' => $montantStock,
                    'compte_gl' => $compteGl,
                    'date_souhaitee_execution' => $dateExec !== '' ? $dateExec : null,
                    'justification' => $justification !== '' ? $justification : null,
                    'responsable' => $responsable,
                    'is_global' => true,
                    'global_line_id' => null,
                    'agence_id' => null,
                ];
                if ($categorieId !== null) {
                    $payload['categorie_depense_id'] = $categorieId;
                }

                if ($existing) {
                    $existing->update($payload);
                    $lastGlobal = $existing->fresh();
                    $updated++;
                } else {
                    $lastGlobal = BudgetLine::create(array_merge($payload, [
                        'budget_id' => $budget->id,
                        'code' => $code,
                        'categorie_depense_id' => $categorieId,
                    ]));
                    $created++;
                }
            }

            fclose($handle);

            $budget->update([
                'total_amount' => $budget->lines()->where('is_global', true)->sum('montant_estime'),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($handle);
            report($e);

            return back()->with('error', 'Import échoué : '.$e->getMessage());
        }

        $msg = "Import terminé : {$created} créée(s), {$updated} mise(s) à jour";
        if ($skipped > 0) {
            $msg .= ", {$skipped} ignorée(s)";
        }
        $msg .= '.';

        $redirect = redirect()->route('budgets.index', [
            'department_id' => $validated['department_id'],
            'year' => $validated['year'],
        ])->with('success', $msg);

        if ($errors !== []) {
            $redirect->with('warning', implode(' ', array_slice($errors, 0, 5)));
        }

        return $redirect;
    }

    private function parseImportAmount(?string $value): float
    {
        if ($value === null || trim($value) === '' || trim($value) === '-') {
            return 0.0;
        }

        $normalized = str_replace(["\u{00A0}", ' ', ' '], '', $value);
        $normalized = str_replace(',', '.', $normalized);
        $normalized = preg_replace('/[^\d.\-]/', '', $normalized) ?? '0';

        return (float) $normalized;
    }

    // ────────────────────────────────────────────────────────────────────────────
    // Méthodes privées
    // ────────────────────────────────────────────────────────────────────────────

    /**
     * Années sélectionnables : plage calendaire + années déjà présentes en base.
     *
     * @return list<int>
     */
    private function availableYears(): array
    {
        $current = (int) date('Y');
        $range = range($current - 2, $current + 3);

        $fromDb = Budget::query()
            ->select('year')
            ->distinct()
            ->pluck('year')
            ->map(fn ($y) => (int) $y)
            ->all();

        $years = array_values(array_unique(array_merge($range, $fromDb)));
        rsort($years);

        return $years;
    }

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
