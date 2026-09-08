<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\PodLigneComptable;
use App\Models\PodProduit;
use App\Models\PodTrancheFrais;
use App\Support\PodFicheParametrageImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PodProduitController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $filters = array_filter([
            'q' => $request->input('q'),
            'statut' => $request->input('statut'),
            'initiateur' => $request->input('initiateur'),
            'actif' => $request->input('actif'),
        ], static fn ($v) => $v !== null && $v !== '');

        $query = PodProduit::query()->withCount(['tranches', 'lignesComptables']);

        if (! empty($filters['q'])) {
            $term = '%'.trim((string) $filters['q']).'%';
            $query->where(function ($w) use ($term) {
                $w->where('code', 'like', $term)
                    ->orWhere('libelle', 'like', $term)
                    ->orWhere('code_taf', 'like', $term)
                    ->orWhere('compte_produit', 'like', $term);
            });
        }

        if (! empty($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }

        if (! empty($filters['initiateur'])) {
            $query->where('initiateur', $filters['initiateur']);
        }

        if (array_key_exists('actif', $filters)) {
            $query->where('actif', filter_var($filters['actif'], FILTER_VALIDATE_BOOLEAN));
        }

        $produits = $query
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (PodProduit $p) => $this->listPayload($p));

        return Inertia::render('Pod/Index', [
            'produits' => $produits,
            'filters' => $filters,
            'tauxTafDefaut' => (float) AppSetting::get('pod.taux_taf_defaut', 10),
            'statuts' => [
                PodProduit::STATUT_BROUILLON,
                PodProduit::STATUT_VALIDE,
                PodProduit::STATUT_PRODUCTION,
            ],
            'modesFrais' => [
                PodProduit::MODE_FIXE,
                PodProduit::MODE_TRANCHE,
                PodProduit::MODE_TAUX,
                PodProduit::MODE_MIXTE,
                PodProduit::MODE_MANUEL,
                PodProduit::MODE_GRATUIT,
            ],
        ]);
    }

    public function create(): InertiaResponse
    {
        return Inertia::render('Pod/Form', [
            'produit' => null,
            'tauxTafDefaut' => (float) AppSetting::get('pod.taux_taf_defaut', 10),
            ...$this->formMeta(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateProduit($request);

        $produit = DB::transaction(function () use ($request, $validated) {
            $produit = PodProduit::create([
                ...$this->produitAttributes($validated),
                'created_by_user_id' => auth()->id(),
                'updated_by_user_id' => auth()->id(),
            ]);

            $this->syncChildren($produit, $validated);

            return $produit;
        });

        return redirect()
            ->route('pod.produits.show', $produit)
            ->with('success', 'Produit POD créé.');
    }

    public function show(PodProduit $produit): InertiaResponse
    {
        $produit->load(['tranches', 'lignesComptables', 'createdBy:id,name', 'updatedBy:id,name']);

        return Inertia::render('Pod/Show', [
            'produit' => $this->detailPayload($produit),
            'tauxTafDefaut' => (float) AppSetting::get('pod.taux_taf_defaut', 10),
        ]);
    }

    public function edit(PodProduit $produit): InertiaResponse
    {
        $produit->load(['tranches', 'lignesComptables']);

        return Inertia::render('Pod/Form', [
            'produit' => $this->detailPayload($produit),
            'tauxTafDefaut' => (float) AppSetting::get('pod.taux_taf_defaut', 10),
            ...$this->formMeta(),
        ]);
    }

    public function update(Request $request, PodProduit $produit): RedirectResponse
    {
        $validated = $this->validateProduit($request, $produit);

        DB::transaction(function () use ($produit, $validated) {
            $produit->update([
                ...$this->produitAttributes($validated),
                'updated_by_user_id' => auth()->id(),
            ]);

            $this->syncChildren($produit, $validated);
        });

        return redirect()
            ->route('pod.produits.show', $produit)
            ->with('success', 'Produit POD mis à jour.');
    }

    public function destroy(PodProduit $produit): RedirectResponse
    {
        $produit->delete();

        return redirect()
            ->route('pod.produits.index')
            ->with('success', 'Produit POD supprimé.');
    }

    public function updateStatut(Request $request, PodProduit $produit): RedirectResponse
    {
        $validated = $request->validate([
            'statut' => ['required', 'in:brouillon,valide,production'],
        ]);

        $produit->update([
            'statut' => $validated['statut'],
            'updated_by_user_id' => auth()->id(),
        ]);

        return redirect()->back()->with('success', 'Statut mis à jour.');
    }

    public function updateTauxTafDefaut(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'taux_taf_defaut' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        AppSetting::updateOrCreate(
            ['key' => 'pod.taux_taf_defaut'],
            [
                'value' => (string) $validated['taux_taf_defaut'],
                'label' => 'Taux TAF par défaut (POD)',
                'description' => 'Taux de taxe sur activité financière appliqué si non défini sur le produit.',
                'type' => 'number',
            ]
        );

        return redirect()->back()->with('success', 'Taux TAF par défaut enregistré.');
    }

    public function importForm(): InertiaResponse
    {
        return Inertia::render('Pod/Import', [
            'templateHint' => 'Fiche paramétrage (colonnes : OPERATION, PRIX UNITAIRE, COMPTE PRODUIT, NEW CODE PRODUIT, LIBELLE ECRITURE PRODUIT, NEW CODE TAF, LIBELLE ECRITURE TAF, INITIATEUR)',
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'fichier' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $path = $request->file('fichier')->getRealPath();
        $stats = PodFicheParametrageImport::fromPath((string) $path, auth()->user());

        $msg = sprintf(
            'Import terminé : %d créé(s), %d mis à jour, %d ignoré(s).',
            $stats['created'],
            $stats['updated'],
            $stats['skipped']
        );

        $redirect = redirect()->route('pod.produits.index')->with('success', $msg);

        if ($stats['errors'] !== []) {
            $redirect->with('warning', implode(' ', array_slice($stats['errors'], 0, 5)));
        }

        return $redirect;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateProduit(Request $request, ?PodProduit $produit = null): array
    {
        $codeRule = $produit
            ? 'required|string|max:50|unique:pod_produits,code,'.$produit->id
            : 'required|string|max:50|unique:pod_produits,code';

        return $request->validate([
            'code' => $codeRule,
            'libelle' => ['required', 'string', 'max:255'],
            'type_operation' => ['nullable', 'string', 'max:255'],
            'devise' => ['required', 'string', 'max:10'],
            'code_taf' => ['nullable', 'string', 'max:50'],
            'libelle_ecriture_produit' => ['nullable', 'string', 'max:255'],
            'libelle_ecriture_taf' => ['nullable', 'string', 'max:255'],
            'compte_produit' => ['nullable', 'string', 'max:50'],
            'compte_taf' => ['nullable', 'string', 'max:50'],
            'compte_client_mask' => ['nullable', 'string', 'max:50'],
            'initiateur' => ['nullable', 'string', 'max:50'],
            'validateur' => ['nullable', 'string', 'max:50'],
            'mode_frais' => ['required', 'in:fixe,tranche,taux,mixte,manuel,gratuit'],
            'frais_fixe' => ['nullable', 'numeric', 'min:0'],
            'taux_frais' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'frais_min' => ['nullable', 'numeric', 'min:0'],
            'frais_max' => ['nullable', 'numeric', 'min:0'],
            'taux_taf' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'base_calcul' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'statut' => ['required', 'in:brouillon,valide,production'],
            'actif' => ['boolean'],
            'tranches' => ['nullable', 'array'],
            'tranches.*.libelle' => ['nullable', 'string', 'max:255'],
            'tranches.*.montant_min' => ['nullable', 'numeric', 'min:0'],
            'tranches.*.montant_max' => ['nullable', 'numeric', 'min:0'],
            'tranches.*.frais_fixe' => ['nullable', 'numeric', 'min:0'],
            'tranches.*.taux' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tranches.*.frais_min' => ['nullable', 'numeric', 'min:0'],
            'tranches.*.frais_max' => ['nullable', 'numeric', 'min:0'],
            'lignes' => ['nullable', 'array'],
            'lignes.*.sens' => ['required_with:lignes', 'in:D,C'],
            'lignes.*.compte' => ['required_with:lignes', 'string', 'max:50'],
            'lignes.*.libelle_ecriture' => ['nullable', 'string', 'max:255'],
            'lignes.*.nature_compte' => ['nullable', 'in:client,produit,taf,contrepartie,autre'],
            'lignes.*.type_montant' => ['nullable', 'in:frais_ht,taf,frais_plus_taf,montant_operation,fixe,pourcentage'],
            'lignes.*.montant_fixe' => ['nullable', 'numeric', 'min:0'],
            'lignes.*.taux' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lignes.*.obligatoire' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function produitAttributes(array $validated): array
    {
        return [
            'code' => $validated['code'],
            'libelle' => $validated['libelle'],
            'type_operation' => $validated['type_operation'] ?? null,
            'devise' => $validated['devise'],
            'code_taf' => $validated['code_taf'] ?? null,
            'libelle_ecriture_produit' => $validated['libelle_ecriture_produit'] ?? null,
            'libelle_ecriture_taf' => $validated['libelle_ecriture_taf'] ?? null,
            'compte_produit' => $validated['compte_produit'] ?? null,
            'compte_taf' => $validated['compte_taf'] ?? null,
            'compte_client_mask' => $validated['compte_client_mask'] ?? '251XXXXXX',
            'initiateur' => $validated['initiateur'] ?? null,
            'validateur' => $validated['validateur'] ?? null,
            'mode_frais' => $validated['mode_frais'],
            'frais_fixe' => $validated['frais_fixe'] ?? null,
            'taux_frais' => $validated['taux_frais'] ?? null,
            'frais_min' => $validated['frais_min'] ?? null,
            'frais_max' => $validated['frais_max'] ?? null,
            'taux_taf' => $validated['taux_taf'] ?? null,
            'base_calcul' => $validated['base_calcul'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'statut' => $validated['statut'],
            'actif' => (bool) ($validated['actif'] ?? true),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncChildren(PodProduit $produit, array $validated): void
    {
        $produit->tranches()->delete();
        $produit->lignesComptables()->delete();

        foreach ($validated['tranches'] ?? [] as $order => $tranche) {
            PodTrancheFrais::create([
                'pod_produit_id' => $produit->id,
                'libelle' => $tranche['libelle'] ?? null,
                'montant_min' => $tranche['montant_min'] ?? 0,
                'montant_max' => $tranche['montant_max'] ?? null,
                'frais_fixe' => $tranche['frais_fixe'] ?? null,
                'taux' => $tranche['taux'] ?? null,
                'frais_min' => $tranche['frais_min'] ?? null,
                'frais_max' => $tranche['frais_max'] ?? null,
                'sort_order' => $order,
            ]);
        }

        foreach ($validated['lignes'] ?? [] as $order => $ligne) {
            PodLigneComptable::create([
                'pod_produit_id' => $produit->id,
                'sort_order' => $order,
                'sens' => $ligne['sens'],
                'compte' => $ligne['compte'],
                'libelle_ecriture' => $ligne['libelle_ecriture'] ?? null,
                'nature_compte' => $ligne['nature_compte'] ?? 'autre',
                'type_montant' => $ligne['type_montant'] ?? 'frais_ht',
                'montant_fixe' => $ligne['montant_fixe'] ?? null,
                'taux' => $ligne['taux'] ?? null,
                'obligatoire' => (bool) ($ligne['obligatoire'] ?? true),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function listPayload(PodProduit $p): array
    {
        return [
            'id' => $p->id,
            'code' => $p->code,
            'libelle' => $p->libelle,
            'code_taf' => $p->code_taf,
            'compte_produit' => $p->compte_produit,
            'initiateur' => $p->initiateur,
            'mode_frais' => $p->mode_frais,
            'frais_fixe' => $p->frais_fixe,
            'statut' => $p->statut,
            'actif' => $p->actif,
            'tranches_count' => $p->tranches_count ?? 0,
            'lignes_count' => $p->lignes_comptables_count ?? 0,
            'show_url' => route('pod.produits.show', $p),
            'edit_url' => route('pod.produits.edit', $p),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailPayload(PodProduit $p): array
    {
        return [
            'id' => $p->id,
            'code' => $p->code,
            'libelle' => $p->libelle,
            'type_operation' => $p->type_operation,
            'devise' => $p->devise,
            'code_taf' => $p->code_taf,
            'libelle_ecriture_produit' => $p->libelle_ecriture_produit,
            'libelle_ecriture_taf' => $p->libelle_ecriture_taf,
            'compte_produit' => $p->compte_produit,
            'compte_taf' => $p->compte_taf,
            'compte_client_mask' => $p->compte_client_mask,
            'initiateur' => $p->initiateur,
            'validateur' => $p->validateur,
            'mode_frais' => $p->mode_frais,
            'frais_fixe' => $p->frais_fixe,
            'taux_frais' => $p->taux_frais,
            'frais_min' => $p->frais_min,
            'frais_max' => $p->frais_max,
            'taux_taf' => $p->taux_taf,
            'taux_taf_effectif' => $p->tauxTafEffectif(),
            'base_calcul' => $p->base_calcul,
            'notes' => $p->notes,
            'statut' => $p->statut,
            'actif' => $p->actif,
            'created_by_name' => $p->createdBy?->name,
            'updated_by_name' => $p->updatedBy?->name,
            'tranches' => $p->tranches->map(fn (PodTrancheFrais $t) => [
                'id' => $t->id,
                'libelle' => $t->libelle,
                'montant_min' => $t->montant_min,
                'montant_max' => $t->montant_max,
                'frais_fixe' => $t->frais_fixe,
                'taux' => $t->taux,
                'frais_min' => $t->frais_min,
                'frais_max' => $t->frais_max,
                'sort_order' => $t->sort_order,
            ])->values(),
            'lignes' => $p->lignesComptables->map(fn (PodLigneComptable $l) => [
                'id' => $l->id,
                'sens' => $l->sens,
                'compte' => $l->compte,
                'libelle_ecriture' => $l->libelle_ecriture,
                'nature_compte' => $l->nature_compte,
                'type_montant' => $l->type_montant,
                'montant_fixe' => $l->montant_fixe,
                'taux' => $l->taux,
                'obligatoire' => $l->obligatoire,
                'sort_order' => $l->sort_order,
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formMeta(): array
    {
        return [
            'statuts' => [
                PodProduit::STATUT_BROUILLON,
                PodProduit::STATUT_VALIDE,
                PodProduit::STATUT_PRODUCTION,
            ],
            'modesFrais' => [
                PodProduit::MODE_FIXE,
                PodProduit::MODE_TRANCHE,
                PodProduit::MODE_TAUX,
                PodProduit::MODE_MIXTE,
                PodProduit::MODE_MANUEL,
                PodProduit::MODE_GRATUIT,
            ],
            'naturesCompte' => ['client', 'produit', 'taf', 'contrepartie', 'autre'],
            'typesMontant' => ['frais_ht', 'taf', 'frais_plus_taf', 'montant_operation', 'fixe', 'pourcentage'],
            'initiateurs' => ['CC', 'OPS', 'FINANCE'],
        ];
    }
}
