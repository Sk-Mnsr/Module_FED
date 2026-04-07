<?php

namespace App\Http\Controllers;

use App\Models\Fed;
use App\Models\FedAttachment;
use App\Models\FedItem;
use App\Models\BudgetLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class FedController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);

        $feds = Fed::where('requester_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return Inertia::render('feds/Index', [
            'feds' => $feds,
        ]);
    }

    public function create(Request $request)
    {
        $departments = \App\Models\Department::orderBy('name')->get(['id', 'name']);
        $budgetLines = BudgetLine::where('is_reclassified', false)
            ->with('agence:id,nom,code')
            ->get(['id', 'code', 'label', 'montant_estime', 'budget_id', 'is_global', 'global_line_id', 'agence_id'])
            ->map(function (BudgetLine $line) {
            return [
                'id' => $line->id,
                'code' => $line->code,
                'label' => $line->label,
                'montant_estime' => $line->montant_estime,
                'year' => $line->budget?->year,
                'department_name' => $line->budget?->department?->name,
                'is_global' => $line->is_global,
                'global_line_id' => $line->global_line_id,
                'agence_name' => $line->agence?->nom,
            ];
        })->values();


        $userDepartment = $request->user()->profil?->departement;

        return Inertia::render('feds/Create', [
            'departments' => $departments,
            'budgetLines' => $budgetLines,
            'userDepartment' => $userDepartment,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->getDraftRules());

        DB::beginTransaction();

        $fedData = $this->extractFedData($validated);
        $fedData = $this->applyRequesterDefaults($fedData, $request);

        $fed = Fed::create(array_merge(
            $fedData,
            [
                'code' => Fed::generateCode(
                    $fedData['department'] ?? null,
                    $fedData['date'] ?? null
                ),
                'requester_id' => $request->user()->id,
                'status' => Fed::STATUS_PENDING_VALIDATION,
            ]
        ));

        $this->syncItems($fed, $validated['items'] ?? null);
        $this->storeAttachments($fed, $request);

        DB::commit();

        return redirect(route('feds.edit', $fed) . '?submit=1')
            ->with('success', 'Demande créée. Soumettez pour validation.');
    }

    public function show(Request $request, Fed $fed)
    {
        if ($fed->requester_id !== $request->user()->id) {
            abort(403);
        }

        $fed->load(['items.budgetLine', 'items.entities.budgetLine.agence', 'attachments']);

        return Inertia::render('feds/Show', [
            'fed' => $fed,
        ]);
    }

    public function edit(Request $request, Fed $fed)
    {
        if ($fed->requester_id !== $request->user()->id) {
            abort(403);
        }

        $fed->load(['items.budgetLine', 'items.entities', 'attachments']);


        return Inertia::render('feds/Edit', [
            'fed' => $fed,
            'canEdit' => $fed->isEditableByRequester(),
            'authSignature' => $request->user()->signature,
            'departments' => \App\Models\Department::orderBy('name')->get(['id', 'name']),
            'budgetLines' => BudgetLine::where('is_reclassified', false)
                ->with(['budget.department', 'agence:id,nom,code'])
                ->get()
                ->map(function (BudgetLine $line) {
                    return [
                        'id' => $line->id,
                        'code' => $line->code,
                        'label' => $line->label,
                        'montant_estime' => $line->montant_estime,
                        'year' => $line->budget?->year,
                        'department_name' => $line->budget?->department?->name,
                        'is_global' => $line->is_global,
                        'global_line_id' => $line->global_line_id,
                        'agence_name' => $line->agence?->nom,
                    ];
                })->values(),
            'userDepartment' => $request->user()->profil?->departement,
        ]);
    }

    public function update(Request $request, Fed $fed)
    {
        if ($fed->requester_id !== $request->user()->id) {
            abort(403);
        }

        if (!$fed->isEditableByRequester()) {
            return redirect()->route('feds.index')
                ->with('error', "Cette FED n'est plus modifiable.");
        }

        $validated = $request->validate($this->getDraftRules());

        DB::beginTransaction();

        $fed->update($this->extractFedData($validated, true));

        if (array_key_exists('items', $validated)) {
            $this->syncItems($fed, $validated['items']);
        }

        $this->deleteAttachments($fed, $validated['removed_attachment_ids'] ?? []);
        $this->storeAttachments($fed, $request);

        DB::commit();

        return redirect()->route('feds.edit', $fed)
            ->with('success', 'Modifications enregistrées.');
    }

    public function destroy(Request $request, Fed $fed)
    {
        if ($fed->requester_id !== $request->user()->id) {
            abort(403);
        }

        if (!$fed->isEditableByRequester()) {
            return redirect()->route('feds.index')
                ->with('error', "Cette FED n'est plus supprimable.");
        }

        DB::beginTransaction();

        foreach ($fed->attachments as $attachment) {
            $this->deleteAttachmentFile($attachment);
        }

        $fed->delete();

        DB::commit();

        return redirect()->route('feds.index')
            ->with('success', 'FED supprimée.');
    }

    public function submit(Request $request, Fed $fed)
    {
        if ($fed->requester_id !== $request->user()->id) {
            abort(403);
        }

        if (!$fed->isEditableByRequester()) {
            return redirect()->route('feds.index')
                ->with('error', "Cette FED n'est plus soumissible.");
        }

        $payload = $this->buildSubmitPayload($fed, $request);

        $validated = validator($payload, $this->getSubmitRules())->validate();

        $signature = $validated['requester_signature'] ?? null;
        if (!$signature && $request->boolean('use_saved_signature')) {
            $signature = $request->user()->signature;
        }
        if (!$signature) {
            return redirect()->back()->withErrors(['requester_signature' => 'Signature requise.']);
        }

        DB::beginTransaction();

        $fed->update(array_merge(
            $this->extractFedData($validated, true),
            [
                'status' => Fed::STATUS_PENDING_VALIDATION,
                'submitted_at' => now(),
                'requester_signature' => $signature,
                'requester_signed_at' => now(),
            ]
        ));

        if ($request->has('items')) {
            $this->syncItems($fed, $validated['items'] ?? []);
        }

        $this->deleteAttachments($fed, $validated['removed_attachment_ids'] ?? []);
        $this->storeAttachments($fed, $request);

        DB::commit();

        return redirect()->route('feds.edit', $fed)
            ->with('success', 'FED soumise au N+1.');
    }

    private function getDraftRules(): array
    {
        return [
            'date' => 'nullable|date',
            'demandeur' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'fonction' => 'nullable|string|max:255',
            'beneficiaire' => ['nullable', function ($attribute, $value, $fail) {
                if ($value !== null && $value !== '' && !is_array($value) && !is_string($value)) {
                    $fail('Le champ bénéficiaire doit être une liste ou un texte.');
                }
            }],
            'motive' => 'nullable|string',
            'estimated_total' => 'nullable|numeric|min:0',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'items.*.label' => 'nullable|string|max:255',
            'items.*.budget_line_id' => 'nullable|integer|exists:budget_lines,id',
            'items.*.quantity' => 'nullable|numeric|min:0.01',
            'items.*.description' => 'nullable|string',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.total_price' => 'nullable|numeric|min:0',
            'items.*.entities' => 'nullable|array',
            'items.*.entities.*.budget_line_id' => 'required|integer|exists:budget_lines,id',
            'items.*.entities.*.quantity' => 'required|numeric|min:0',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240',
            'removed_attachment_ids' => 'nullable|array',
            'removed_attachment_ids.*' => 'integer',
        ];
    }

    private function getSubmitRules(): array
    {
        return [
            'date' => 'required|date',
            'demandeur' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'fonction' => 'required|string|max:255',
            'beneficiaire' => ['nullable', function ($attribute, $value, $fail) {
                if ($value !== null && $value !== '' && !is_array($value) && !is_string($value)) {
                    $fail('Le champ bénéficiaire doit être une liste ou un texte.');
                }
            }],
            'requester_signature' => 'required_without:use_saved_signature|nullable|string',
            'use_saved_signature' => 'nullable|boolean',
            'motive' => 'required|string',
            'estimated_total' => 'nullable|numeric|min:0',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'items.*.label' => 'required|string|max:255',
            'items.*.budget_line_id' => 'required|integer|exists:budget_lines,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.description' => 'nullable|string',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.total_price' => 'nullable|numeric|min:0',
            'items.*.entities' => 'nullable|array',
            'items.*.entities.*.budget_line_id' => 'required|integer|exists:budget_lines,id',
            'items.*.entities.*.quantity' => 'required|numeric|min:0',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240',
            'removed_attachment_ids' => 'nullable|array',
            'removed_attachment_ids.*' => 'integer',
        ];
    }

    private function extractFedData(array $data, bool $onlyProvided = false): array
    {
        $fields = [
            'date',
            'demandeur',
            'department',
            'fonction',
            'beneficiaire',
            'motive',
            'estimated_total',
            'priority',
        ];

        $payload = [];

        foreach ($fields as $field) {
            if ($onlyProvided && !array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field] ?? null;

            if ($field === 'beneficiaire') {
                $payload[$field] = $this->normalizeBeneficiaires($value);
                continue;
            }

            $payload[$field] = $value;
        }

        if (!$onlyProvided && empty($payload['priority'])) {
            $payload['priority'] = 'normal';
        }

        return $payload;
    }

    private function syncItems(Fed $fed, ?array $items): void
    {
        if ($items === null) {
            return;
        }

        $normalized = $this->normalizeItems($items);

        FedItem::where('fed_id', $fed->id)->delete();

        foreach ($normalized as $item) {
            $fedItem = FedItem::create([
                'fed_id' => $fed->id,
                'budget_line_id' => $item['budget_line_id'] ?? null,
                'label' => $item['label'],
                'quantity' => $item['quantity'],
                'description' => $item['description'] ?? null,
                'unit_price' => $item['unit_price'] ?? null,
                'total_price' => $item['total_price'] ?? null,
            ]);

            if (!empty($item['entities'])) {
                foreach ($item['entities'] as $entity) {
                    if ($entity['quantity'] > 0) {
                        $fedItem->entities()->create([
                            'budget_line_id' => $entity['budget_line_id'],
                            'quantity' => $entity['quantity'],
                        ]);
                    }
                }
            }
        }
    }

    private function normalizeItems(array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $label = trim((string) ($item['label'] ?? ''));
            $quantity = $item['quantity'] ?? null;

            if ($label === '' && $quantity === null) {
                continue;
            }

            $normalized[] = [
                'label' => $label,
                'budget_line_id' => $item['budget_line_id'] ?? null,
                'quantity' => $quantity ?? 1,
                'description' => $item['description'] ?? null,
                'unit_price' => $item['unit_price'] ?? null,
                'total_price' => $item['total_price'] ?? null,
                'entities' => $item['entities'] ?? [],
            ];
        }

        return $normalized;
    }

    private function storeAttachments(Fed $fed, Request $request): void
    {
        if (!$request->hasFile('attachments')) {
            return;
        }

        foreach ($request->file('attachments', []) as $file) {
            if (!$file || !$file->isValid()) {
                continue;
            }

            $path = $file->store("feds/{$fed->id}", 'public');

            FedAttachment::create([
                'fed_id' => $fed->id,
                'uploaded_by' => $request->user()->id,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }

    private function deleteAttachments(Fed $fed, array $attachmentIds): void
    {
        if (!$attachmentIds) {
            return;
        }

        $attachments = $fed->attachments()->whereIn('id', $attachmentIds)->get();

        foreach ($attachments as $attachment) {
            $this->deleteAttachmentFile($attachment);
            $attachment->delete();
        }
    }

    private function deleteAttachmentFile(FedAttachment $attachment): void
    {
        if ($attachment->path) {
            Storage::disk('public')->delete($attachment->path);
        }
    }

    private function buildSubmitPayload(Fed $fed, Request $request): array
    {
        $payload = [
            'date' => optional($fed->date)->toDateString(),
            'demandeur' => $fed->demandeur,
            'department' => $fed->department,
            'fonction' => $fed->fonction,
            'beneficiaire' => $this->splitBeneficiaires($fed->beneficiaire),
            'motive' => $fed->motive,
            'estimated_total' => $fed->estimated_total,
            'priority' => $fed->priority ?? 'normal',
            'items' => $fed->items->map(function (FedItem $item) {
                return [
                    'label' => $item->label,
                    'budget_line_id' => $item->budget_line_id,
                    'quantity' => $item->quantity,
                    'description' => $item->description,
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->total_price,
                    'entities' => $item->entities->map(function ($entity) {
                        return [
                            'budget_line_id' => $entity->budget_line_id,
                            'quantity' => $entity->quantity,
                        ];
                    })->toArray(),
                ];
            })->toArray(),
        ];

        $payload = array_merge($payload, $this->extractFedData($request->all(), true));

        if ($request->has('items')) {
            $payload['items'] = $request->input('items');
        }

        if ($request->has('removed_attachment_ids')) {
            $payload['removed_attachment_ids'] = $request->input('removed_attachment_ids');
        }

        if ($request->boolean('use_saved_signature')) {
            $signature = $request->user()->signature;
            if ($signature) {
                $payload['requester_signature'] = $signature;
                $payload['use_saved_signature'] = true;
            }
        } elseif ($request->has('requester_signature')) {
            $payload['requester_signature'] = $request->input('requester_signature');
        }

        return $payload;
    }

    private function normalizeBeneficiaires($value): ?string
    {
        $items = $this->splitBeneficiaires($value);

        return $items ? implode('; ', $items) : null;
    }

    private function splitBeneficiaires($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value), fn ($item) => $item !== ''));
        }

        $parts = preg_split('/\s*[;,]\s*/', (string) $value);

        return array_values(array_filter(array_map('trim', $parts), fn ($item) => $item !== ''));
    }

    private function applyRequesterDefaults(array $data, Request $request): array
    {
        if (empty($data['demandeur'])) {
            $data['demandeur'] = $request->user()?->name;
        }

        if (empty($data['fonction'])) {
            $data['fonction'] = $request->user()?->fonction;
        }

        return $data;
    }
}

