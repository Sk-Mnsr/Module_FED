<?php

namespace App\Http\Controllers;

use App\Models\PodDataTable;
use App\Models\PodDataTableColumn;
use App\Models\PodDataTableRow;
use App\Support\PodDataTableImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PodDataTableController extends Controller
{
    public function options(Request $request, string $code): JsonResponse
    {
        $table = PodDataTable::query()
            ->where('code', strtoupper(trim($code)))
            ->where('actif', true)
            ->firstOrFail();

        $validated = $request->validate([
            'colonne_valeur' => ['required', 'string', 'max:80'],
            'colonne_libelle' => ['required', 'string', 'max:80'],
            'colonnes_desactivees' => ['nullable', 'array'],
            'colonnes_desactivees.*' => ['string', 'max:80'],
        ]);

        return response()->json([
            'valeurs' => $table->optionsFor(
                $validated['colonne_valeur'],
                $validated['colonne_libelle'],
                $validated['colonnes_desactivees'] ?? [],
            ),
        ]);
    }

    public function index(Request $request): InertiaResponse
    {
        $q = trim((string) $request->input('q', ''));

        $tables = PodDataTable::query()
            ->withCount(['columns', 'rows'])
            ->when($q !== '', function ($query) use ($q) {
                $term = '%'.$q.'%';
                $query->where(function ($w) use ($term) {
                    $w->where('code', 'like', $term)->orWhere('libelle', 'like', $term);
                });
            })
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (PodDataTable $t) => [
                'id' => $t->id,
                'code' => $t->code,
                'libelle' => $t->libelle,
                'actif' => $t->actif,
                'columns_count' => $t->columns_count,
                'rows_count' => $t->rows_count,
                'show_url' => route('pod.tables.show', $t),
                'edit_url' => route('pod.tables.edit', $t),
            ]);

        return Inertia::render('Pod/Tables/Index', [
            'tables' => $tables,
            'filters' => ['q' => $q],
        ]);
    }

    public function create(): InertiaResponse
    {
        return Inertia::render('Pod/Tables/Form', [
            'table' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateTable($request);

        $table = DB::transaction(function () use ($validated) {
            $table = PodDataTable::create([
                'code' => strtoupper(trim($validated['code'])),
                'libelle' => $validated['libelle'],
                'description' => $validated['description'] ?? null,
                'actif' => (bool) ($validated['actif'] ?? true),
                'created_by_user_id' => auth()->id(),
                'updated_by_user_id' => auth()->id(),
            ]);

            $this->syncColumns($table, $validated['columns'] ?? []);

            return $table;
        });

        return redirect()
            ->route('pod.tables.show', $table)
            ->with('success', 'Table source créée.');
    }

    public function show(PodDataTable $podDataTable): InertiaResponse
    {
        $podDataTable->load(['columns', 'rows' => fn ($q) => $q->orderBy('sort_order')->limit(200)]);

        return Inertia::render('Pod/Tables/Show', [
            'table' => $this->detailPayload($podDataTable),
        ]);
    }

    public function edit(PodDataTable $podDataTable): InertiaResponse
    {
        $podDataTable->load('columns');

        return Inertia::render('Pod/Tables/Form', [
            'table' => $this->detailPayload($podDataTable, includeRows: false),
        ]);
    }

    public function update(Request $request, PodDataTable $podDataTable): RedirectResponse
    {
        $validated = $this->validateTable($request, $podDataTable);

        DB::transaction(function () use ($podDataTable, $validated) {
            $podDataTable->update([
                'code' => strtoupper(trim($validated['code'])),
                'libelle' => $validated['libelle'],
                'description' => $validated['description'] ?? null,
                'actif' => (bool) ($validated['actif'] ?? true),
                'updated_by_user_id' => auth()->id(),
            ]);

            $this->syncColumns($podDataTable, $validated['columns'] ?? []);
        });

        return redirect()
            ->route('pod.tables.show', $podDataTable)
            ->with('success', 'Table source mise à jour.');
    }

    public function destroy(PodDataTable $podDataTable): RedirectResponse
    {
        $podDataTable->delete();

        return redirect()
            ->route('pod.tables.index')
            ->with('success', 'Table source supprimée.');
    }

    public function import(Request $request, PodDataTable $podDataTable): RedirectResponse
    {
        $request->validate([
            'fichier' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'replace' => ['nullable', 'boolean'],
        ]);

        $path = $request->file('fichier')->getRealPath();
        $stats = PodDataTableImport::fromPath(
            $podDataTable,
            (string) $path,
            $request->boolean('replace', true),
        );

        $msg = sprintf(
            'Import : %d ligne(s), %d colonne(s) ajoutée(s), %d ignorée(s).',
            $stats['created'],
            $stats['updated_columns'],
            $stats['skipped']
        );

        $redirect = redirect()->route('pod.tables.show', $podDataTable)->with('success', $msg);
        if ($stats['errors'] !== []) {
            $redirect->with('warning', implode(' ', array_slice($stats['errors'], 0, 5)));
        }

        return $redirect;
    }

    public function storeRow(Request $request, PodDataTable $podDataTable): RedirectResponse
    {
        $podDataTable->load('columns');
        $rules = ['actif' => ['nullable', 'boolean']];
        foreach ($podDataTable->columns as $col) {
            $rules['data.'.$col->code] = ['nullable', 'string', 'max:500'];
        }

        $validated = $request->validate($rules);
        $data = [];
        foreach ($podDataTable->columns as $col) {
            $data[$col->code] = trim((string) ($validated['data'][$col->code] ?? ''));
        }

        PodDataTableRow::create([
            'pod_data_table_id' => $podDataTable->id,
            'data' => $data,
            'actif' => (bool) ($validated['actif'] ?? true),
            'sort_order' => ((int) $podDataTable->rows()->max('sort_order')) + 1,
        ]);

        return redirect()
            ->route('pod.tables.show', $podDataTable)
            ->with('success', 'Ligne ajoutée.');
    }

    public function destroyRow(PodDataTable $podDataTable, PodDataTableRow $row): RedirectResponse
    {
        if ((int) $row->pod_data_table_id !== (int) $podDataTable->id) {
            abort(404);
        }

        $row->delete();

        return redirect()
            ->route('pod.tables.show', $podDataTable)
            ->with('success', 'Ligne supprimée.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTable(Request $request, ?PodDataTable $table = null): array
    {
        if ($request->has('code')) {
            $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        }

        if ($request->has('columns') && is_array($request->input('columns'))) {
            $request->merge([
                'columns' => collect($request->input('columns'))
                    ->map(function ($c) {
                        if (! is_array($c)) {
                            return $c;
                        }
                        if (isset($c['code'])) {
                            $c['code'] = strtoupper(trim((string) $c['code']));
                        }

                        return $c;
                    })
                    ->all(),
            ]);
        }

        $codeRule = Rule::unique('pod_data_tables', 'code');
        if ($table) {
            $codeRule = $codeRule->ignore($table->id);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z][A-Za-z0-9_]*$/', $codeRule],
            'libelle' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'actif' => ['nullable', 'boolean'],
            'columns' => ['nullable', 'array'],
            'columns.*.code' => ['required_with:columns', 'string', 'max:80', 'regex:/^[A-Za-z][A-Za-z0-9_]*$/'],
            'columns.*.libelle' => ['required_with:columns', 'string', 'max:255'],
            'columns.*.actif' => ['nullable', 'boolean'],
        ]);

        $codes = collect($validated['columns'] ?? [])
            ->map(fn ($c) => strtoupper(trim((string) ($c['code'] ?? ''))))
            ->filter();
        if ($codes->count() !== $codes->unique()->count()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'columns' => 'Chaque code de colonne doit être unique.',
            ]);
        }

        return $validated;
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     */
    private function syncColumns(PodDataTable $table, array $columns): void
    {
        $table->columns()->delete();

        foreach ($columns as $order => $col) {
            PodDataTableColumn::create([
                'pod_data_table_id' => $table->id,
                'code' => strtoupper(trim((string) $col['code'])),
                'libelle' => $col['libelle'],
                'actif' => array_key_exists('actif', $col) ? (bool) $col['actif'] : true,
                'sort_order' => $order,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function detailPayload(PodDataTable $t, bool $includeRows = true): array
    {
        $payload = [
            'id' => $t->id,
            'code' => $t->code,
            'libelle' => $t->libelle,
            'description' => $t->description,
            'actif' => $t->actif,
            'columns' => $t->columns->map(fn (PodDataTableColumn $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'libelle' => $c->libelle,
                'actif' => $c->actif,
                'sort_order' => $c->sort_order,
            ])->values(),
        ];

        if ($includeRows) {
            $payload['rows'] = $t->rows->map(fn (PodDataTableRow $r) => [
                'id' => $r->id,
                'data' => $r->data ?? [],
                'actif' => $r->actif,
                'sort_order' => $r->sort_order,
            ])->values();
            $payload['rows_total'] = $t->rows()->count();
        }

        return $payload;
    }
}
