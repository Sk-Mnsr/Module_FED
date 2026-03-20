<?php

namespace App\Http\Controllers\API;

use App\Models\Fed;
use App\Models\FedAttachment;
use App\Models\FedItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Maravel\Http\Controllers\APIController;

/**
 * @group Fiche d'engagement de dépenses (FED)
 */
class FedController extends APIController
{
    /**
     * Liste des FED du demandeur connecté
     *
     * @response 200
     */
    public function index(Request $request)
    {
        $feds = Fed::with(['items', 'attachments'])
            ->where('requester_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return $this->responseOk(['feds' => $feds]);
    }

    /**
     * Détails d'une FED du demandeur connecté
     *
     * @response 200
     */
    public function show(Request $request, int $id)
    {
        $fed = Fed::with(['items', 'attachments'])
            ->where('requester_id', $request->user()->id)
            ->find($id);

        if (!$fed) {
            return $this->responseError(['id' => ["La FED n'existe pas"]], 404);
        }

        return $this->responseOk(['fed' => $fed]);
    }

    /**
     * Création d'une FED (brouillon)
     *
     * @response 201
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->getDraftRules(), $this->getMessages());
        if ($validator->fails()) {
            return $this->responseError($validator->errors()->toArray(), 400);
        }

        DB::beginTransaction();

        $fedData = $this->extractFedData($request);
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

        $this->syncItems($fed, $request->input('items'));
        $this->syncBudgetLines($fed, $request->input('budget_line_ids', []));
        $this->storeAttachments($fed, $request);

        DB::commit();

        return $this->responseOk([
            'fed' => $fed->load(['items', 'attachments', 'budgetLines']),
        ], status: 201);
    }

    /**
     * Mise à jour d'une FED (avant validation)
     *
     * @response 200
     */
    public function update(Request $request, int $id)
    {
        $fed = Fed::with('attachments')
            ->where('requester_id', $request->user()->id)
            ->find($id);

        if (!$fed) {
            return $this->responseError(['id' => ["La FED n'existe pas"]], 404);
        }

        if (!$fed->isEditableByRequester()) {
            return $this->responseError(['status' => ["Cette FED n'est plus modifiable"]], 403);
        }

        $validator = Validator::make($request->all(), $this->getDraftRules(), $this->getMessages());
        if ($validator->fails()) {
            return $this->responseError($validator->errors()->toArray(), 400);
        }

        DB::beginTransaction();

        $fed->update($this->extractFedData($request, onlyProvided: true));

        if ($request->has('items')) {
            $this->syncItems($fed, $request->input('items'));
        }
        if ($request->has('budget_line_ids')) {
            $this->syncBudgetLines($fed, $request->input('budget_line_ids', []));
        }

        $this->deleteAttachments($fed, $request->input('removed_attachment_ids', []));
        $this->storeAttachments($fed, $request);

        DB::commit();

        return $this->responseOk([
            'fed' => $fed->load(['items', 'attachments']),
        ]);
    }

    /**
     * Suppression d'une FED (avant validation)
     *
     * @response 200
     */
    public function destroy(Request $request, int $id)
    {
        $fed = Fed::with('attachments')
            ->where('requester_id', $request->user()->id)
            ->find($id);

        if (!$fed) {
            return $this->responseError(['id' => ["La FED n'existe pas"]], 404);
        }

        if (!$fed->isEditableByRequester()) {
            return $this->responseError(['status' => ["Cette FED n'est plus supprimable"]], 403);
        }

        DB::beginTransaction();

        foreach ($fed->attachments as $attachment) {
            $this->deleteAttachmentFile($attachment);
        }

        $fed->delete();

        DB::commit();

        return $this->responseOk(['message' => "FED supprimée"]);
    }

    /**
     * Soumission d'une FED au N+1
     *
     * @response 200
     */
    public function submit(Request $request, int $id)
    {
        $fed = Fed::with(['items', 'attachments'])
            ->where('requester_id', $request->user()->id)
            ->find($id);

        if (!$fed) {
            return $this->responseError(['id' => ["La FED n'existe pas"]], 404);
        }

        if (!$fed->isEditableByRequester()) {
            return $this->responseError(['status' => ["Cette FED n'est plus soumissible"]], 403);
        }

        $submitPayload = $this->buildSubmitPayload($fed, $request);

        $validator = Validator::make($submitPayload, $this->getSubmitRules(), $this->getMessages());
        if ($validator->fails()) {
            return $this->responseError($validator->errors()->toArray(), 400);
        }

        DB::beginTransaction();

        $fed->update(array_merge(
            $this->extractFedData($request, onlyProvided: true),
            [
                'status' => Fed::STATUS_PENDING_VALIDATION,
                'submitted_at' => now(),
            ]
        ));

        if ($request->has('items')) {
            $this->syncItems($fed, $request->input('items'));
        }

        $this->deleteAttachments($fed, $request->input('removed_attachment_ids', []));
        $this->storeAttachments($fed, $request);

        DB::commit();

        return $this->responseOk([
            'fed' => $fed->load(['items', 'attachments']),
        ]);
    }

    private function getDraftRules(): array
    {
        return [
            'date' => 'nullable|date',
            'demandeur' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'budget_line_ids' => 'nullable|array',
            'budget_line_ids.*' => 'integer|exists:budget_lines,id',
            'fonction' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'subcategory' => 'nullable|string|max:255',
            'motive' => 'nullable|string',
            'estimated_total' => 'nullable|numeric|min:0',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'items' => 'nullable|array',
            'items.*.label' => 'nullable|string|max:255',
            'items.*.quantity' => 'nullable|numeric|min:0.01',
            'items.*.description' => 'nullable|string',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.total_price' => 'nullable|numeric|min:0',
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
            'budget_line_ids' => 'required|array|min:1',
            'budget_line_ids.*' => 'integer|exists:budget_lines,id',
            'fonction' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'motive' => 'required|string',
            'estimated_total' => 'nullable|numeric|min:0',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'items' => 'required|array|min:1',
            'items.*.label' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.description' => 'nullable|string',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.total_price' => 'nullable|numeric|min:0',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240',
            'removed_attachment_ids' => 'nullable|array',
            'removed_attachment_ids.*' => 'integer',
        ];
    }

    private function getMessages(): array
    {
        return [
            'items.required' => 'Au moins un article est requis.',
            'items.*.label.required' => "L'intitulé de l'article est requis.",
            'items.*.quantity.required' => "La quantité est requise.",
            'items.*.quantity.min' => "La quantité doit être supérieure à 0.",
        ];
    }

    private function extractFedData(Request $request, array $overrides = [], bool $onlyProvided = false): array
    {
        $fields = [
            'date',
            'demandeur',
            'department',
            'fonction',
            'category',
            'subcategory',
            'beneficiaire',
            'motive',
            'estimated_total',
            'priority',
        ];

        $data = [];

        foreach ($fields as $field) {
            if ($onlyProvided && !$request->has($field)) {
                continue;
            }

            if ($field === 'priority' && !$onlyProvided) {
                $data[$field] = $request->input($field, 'normal');
                continue;
            }

            $value = $request->input($field);

            if ($field === 'beneficiaire') {
                $data[$field] = $this->normalizeBeneficiaires($value);
                continue;
            }

            $data[$field] = $value;
        }

        return array_merge($data, $overrides);
    }

    private function syncBudgetLines(Fed $fed, array $ids): void
    {
        $fed->budgetLines()->sync(array_values(array_unique($ids)));
    }

    private function syncItems(Fed $fed, ?array $items): void
    {
        if ($items === null) {
            return;
        }

        $normalizedItems = $this->normalizeItems($items);

        FedItem::where('fed_id', $fed->id)->delete();

        foreach ($normalizedItems as $item) {
            FedItem::create([
                'fed_id' => $fed->id,
                'label' => $item['label'],
                'quantity' => $item['quantity'],
                'description' => $item['description'] ?? null,
                'unit_price' => $item['unit_price'] ?? null,
                'total_price' => $item['total_price'] ?? null,
            ]);
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
                'quantity' => $quantity ?? 1,
                'description' => $item['description'] ?? null,
                'unit_price' => $item['unit_price'] ?? null,
                'total_price' => $item['total_price'] ?? null,
            ];
        }

        return $normalized;
    }

    private function buildSubmitPayload(Fed $fed, Request $request): array
    {
        $payload = [
            'date' => optional($fed->date)->toDateString(),
            'demandeur' => $fed->demandeur,
            'department' => $fed->department,
            'budget_line_ids' => $fed->budgetLines->pluck('id')->toArray(),
            'fonction' => $fed->fonction,
            'category' => $fed->category,
            'subcategory' => $fed->subcategory,
            'beneficiaire' => $this->splitBeneficiaires($fed->beneficiaire),
            'motive' => $fed->motive,
            'estimated_total' => $fed->estimated_total,
            'priority' => $fed->priority ?? 'normal',
            'items' => $this->itemsToArray($fed),
        ];

        $payload = array_merge($payload, $this->extractFedData($request, onlyProvided: true));

        if ($request->has('items')) {
            $payload['items'] = $request->input('items');
        }

        return $payload;
    }

    private function itemsToArray(Fed $fed): array
    {
        return $fed->items->map(function (FedItem $item) {
            return [
                'label' => $item->label,
                'quantity' => $item->quantity,
                'description' => $item->description,
                'unit_price' => $item->unit_price,
                'total_price' => $item->total_price,
            ];
        })->toArray();
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

    private function normalizeBeneficiaires($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            $items = array_values(array_filter(array_map(function ($item) {
                return trim((string) $item);
            }, $value), fn ($item) => $item !== ''));

            return $items ? implode('; ', $items) : null;
        }

        return trim((string) $value) ?: null;
    }

    private function splitBeneficiaires(?string $value): array
    {
        if (!$value) {
            return [];
        }

        $parts = preg_split('/\s*;\s*/', $value);

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

