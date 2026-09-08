<?php

namespace App\Http\Controllers;

use App\Models\PodOperation;
use App\Models\PodOperationLigne;
use App\Models\PodProduit;
use App\Support\PodFraisCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PodOperationController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $filters = array_filter([
            'q' => $request->input('q'),
            'statut' => $request->input('statut'),
        ], static fn ($v) => $v !== null && $v !== '');

        $query = PodOperation::query()
            ->with(['produit:id,code,libelle', 'user:id,name'])
            ->orderByDesc('created_at');

        if (! empty($filters['q'])) {
            $term = '%'.trim((string) $filters['q']).'%';
            $query->where(function ($w) use ($term) {
                $w->where('compte_client', 'like', $term)
                    ->orWhere('reference', 'like', $term)
                    ->orWhere('nom_client', 'like', $term)
                    ->orWhereHas('produit', fn ($p) => $p->where('code', 'like', $term)->orWhere('libelle', 'like', $term));
            });
        }

        if (! empty($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }

        $operations = $query->paginate(20)->withQueryString()->through(fn (PodOperation $op) => [
            'id' => $op->id,
            'reference' => $op->reference,
            'compte_client' => $op->compte_client,
            'nom_client' => $op->nom_client,
            'produit_code' => $op->produit?->code,
            'produit_libelle' => $op->produit?->libelle,
            'frais_ht' => $op->frais_ht,
            'montant_taf' => $op->montant_taf,
            'total_client' => $op->total_client,
            'devise' => $op->devise,
            'statut' => $op->statut,
            'user_name' => $op->user?->name,
            'created_at' => optional($op->created_at)->toIso8601String(),
            'show_url' => route('pod.operations.show', $op),
        ]);

        return Inertia::render('Pod/Operations/Index', [
            'operations' => $operations,
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): InertiaResponse
    {
        $produits = PodProduit::query()
            ->where('actif', true)
            ->orderBy('code')
            ->get(['id', 'code', 'libelle', 'mode_frais', 'frais_fixe', 'initiateur', 'statut', 'base_calcul', 'devise']);

        $selectedId = $request->integer('produit_id') ?: null;
        $selected = null;
        if ($selectedId) {
            $selected = PodProduit::query()
                ->with(['tranches', 'lignesComptables'])
                ->find($selectedId);
        }

        return Inertia::render('Pod/Operations/Create', [
            'produits' => $produits->map(fn (PodProduit $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'libelle' => $p->libelle,
                'mode_frais' => $p->mode_frais,
                'frais_fixe' => $p->frais_fixe,
                'initiateur' => $p->initiateur,
                'statut' => $p->statut,
                'base_calcul' => $p->base_calcul,
                'devise' => $p->devise,
            ]),
            'produit' => $selected ? $this->produitPayload($selected) : null,
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pod_produit_id' => ['required', 'integer', 'exists:pod_produits,id'],
            'compte_client' => ['required', 'string', 'max:50'],
            'montant_demande' => ['nullable', 'numeric', 'min:0'],
            'frais_saisi' => ['nullable', 'numeric', 'min:0'],
        ]);

        $produit = PodProduit::query()->findOrFail((int) $validated['pod_produit_id']);

        try {
            $result = PodFraisCalculator::compute($produit, $validated);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pod_produit_id' => ['required', 'integer', 'exists:pod_produits,id'],
            'compte_client' => ['required', 'string', 'max:50'],
            'code_agence' => ['nullable', 'string', 'max:20'],
            'nom_client' => ['nullable', 'string', 'max:255'],
            'date_valeur' => ['nullable', 'date'],
            'montant_demande' => ['nullable', 'numeric', 'min:0'],
            'frais_saisi' => ['nullable', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:100'],
            'libelle' => ['nullable', 'string', 'max:1000'],
        ]);

        $produit = PodProduit::query()->findOrFail((int) $validated['pod_produit_id']);

        try {
            $calc = PodFraisCalculator::compute($produit, $validated);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withInput()->withErrors(['calcul' => $e->getMessage()]);
        }

        if (! $calc['equilibre']) {
            return redirect()->back()->withInput()->withErrors([
                'calcul' => 'Schéma déséquilibré (débit '.$calc['total_debit'].' ≠ crédit '.$calc['total_credit'].').',
            ]);
        }

        $operation = DB::transaction(function () use ($validated, $produit, $calc) {
            $operation = PodOperation::create([
                'pod_produit_id' => $produit->id,
                'user_id' => auth()->id(),
                'reference' => $validated['reference'] ?? null,
                'compte_client' => $validated['compte_client'],
                'code_agence' => $validated['code_agence'] ?? null,
                'nom_client' => $validated['nom_client'] ?? null,
                'date_valeur' => $validated['date_valeur'] ?? now()->toDateString(),
                'montant_demande' => $validated['montant_demande'] ?? null,
                'frais_ht' => $calc['frais_ht'],
                'taux_taf' => $calc['taux_taf'],
                'montant_taf' => $calc['montant_taf'],
                'total_client' => $calc['total_client'],
                'devise' => $calc['devise'],
                'libelle' => $validated['libelle'] ?? $produit->libelle,
                'calcul_detail' => [
                    'mode_frais' => $calc['mode_frais'],
                    'tranche' => $calc['tranche'],
                    'message' => $calc['message'],
                    'frais_saisi' => $validated['frais_saisi'] ?? null,
                ],
                'statut' => PodOperation::STATUT_BROUILLON,
            ]);

            foreach ($calc['lignes'] as $order => $ligne) {
                PodOperationLigne::create([
                    'pod_operation_id' => $operation->id,
                    'sort_order' => $order,
                    ...$ligne,
                ]);
            }

            return $operation;
        });

        return redirect()
            ->route('pod.operations.show', $operation)
            ->with('success', 'Opération enregistrée (brouillon).');
    }

    public function show(PodOperation $operation): InertiaResponse
    {
        $operation->load(['produit', 'user:id,name', 'lignes']);

        return Inertia::render('Pod/Operations/Show', [
            'operation' => [
                'id' => $operation->id,
                'reference' => $operation->reference,
                'compte_client' => $operation->compte_client,
                'code_agence' => $operation->code_agence,
                'nom_client' => $operation->nom_client,
                'date_valeur' => optional($operation->date_valeur)->toDateString(),
                'montant_demande' => $operation->montant_demande,
                'frais_ht' => $operation->frais_ht,
                'taux_taf' => $operation->taux_taf,
                'montant_taf' => $operation->montant_taf,
                'total_client' => $operation->total_client,
                'devise' => $operation->devise,
                'libelle' => $operation->libelle,
                'statut' => $operation->statut,
                'calcul_detail' => $operation->calcul_detail,
                'user_name' => $operation->user?->name,
                'created_at' => optional($operation->created_at)->toIso8601String(),
                'produit' => [
                    'id' => $operation->produit?->id,
                    'code' => $operation->produit?->code,
                    'libelle' => $operation->produit?->libelle,
                    'mode_frais' => $operation->produit?->mode_frais,
                ],
                'lignes' => $operation->lignes->map(fn (PodOperationLigne $l) => [
                    'sens' => $l->sens,
                    'compte' => $l->compte,
                    'libelle_ecriture' => $l->libelle_ecriture,
                    'nature_compte' => $l->nature_compte,
                    'type_montant' => $l->type_montant,
                    'montant' => $l->montant,
                ])->values(),
            ],
        ]);
    }

    public function destroy(PodOperation $operation): RedirectResponse
    {
        if ($operation->statut !== PodOperation::STATUT_BROUILLON) {
            return redirect()->back()->with('error', 'Seuls les brouillons peuvent être supprimés.');
        }

        $operation->delete();

        return redirect()
            ->route('pod.operations.index')
            ->with('success', 'Opération supprimée.');
    }

    /**
     * @return array<string, mixed>
     */
    private function produitPayload(PodProduit $p): array
    {
        return [
            'id' => $p->id,
            'code' => $p->code,
            'libelle' => $p->libelle,
            'mode_frais' => $p->mode_frais,
            'frais_fixe' => $p->frais_fixe,
            'taux_frais' => $p->taux_frais,
            'taux_taf' => $p->tauxTafEffectif(),
            'base_calcul' => $p->base_calcul,
            'devise' => $p->devise,
            'compte_produit' => $p->compte_produit,
            'compte_taf' => $p->compte_taf,
            'compte_client_mask' => $p->compte_client_mask,
            'libelle_ecriture_produit' => $p->libelle_ecriture_produit,
            'libelle_ecriture_taf' => $p->libelle_ecriture_taf,
            'needs_montant_demande' => in_array($p->mode_frais, [
                PodProduit::MODE_TRANCHE,
                PodProduit::MODE_TAUX,
                PodProduit::MODE_MIXTE,
            ], true),
            'needs_frais_saisi' => $p->mode_frais === PodProduit::MODE_MANUEL,
            'tranches' => $p->tranches->map(fn ($t) => [
                'libelle' => $t->libelle,
                'montant_min' => $t->montant_min,
                'montant_max' => $t->montant_max,
                'frais_fixe' => $t->frais_fixe,
                'taux' => $t->taux,
            ])->values(),
        ];
    }
}
