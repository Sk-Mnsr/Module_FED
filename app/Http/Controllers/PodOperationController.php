<?php

namespace App\Http\Controllers;

use App\Models\PodOperation;
use App\Models\PodOperationEtape;
use App\Models\PodOperationLigne;
use App\Models\PodOperationPiece;
use App\Models\PodProduit;
use App\Models\PodProduitChamp;
use App\Models\User;
use App\Support\ModuleAccess;
use App\Support\PodChampSaisie;
use App\Support\PodConstanteResolver;
use App\Support\PodFraisCalculator;
use App\Support\PodOperationCircuit;
use App\Support\PodProduitWorkflow;
use App\Support\PodRechercheFlexcube;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PodOperationController extends Controller
{
    private const FICHIER_RULES = ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,csv,txt'];

    public function __construct(private readonly PodRechercheFlexcube $recherches) {}

    public function recherche(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pod_produit_id' => ['required', 'integer', 'exists:pod_produits,id'],
            'champ' => ['required', 'string', 'max:80'],
            'champs_saisis' => ['nullable', 'array'],
        ]);

        $produit = PodProduit::query()->with(['champs.ecran', 'scripts'])->findOrFail((int) $validated['pod_produit_id']);
        $champ = $produit->champs->first(fn (PodProduitChamp $c) => strtoupper($c->code) === strtoupper($validated['champ']));
        if (! $champ) {
            return response()->json(['error' => 'Champ inconnu sur ce produit.'], 422);
        }
        if ($champ->ecran && ! PodOperationCircuit::userCanActOnStep($request->user(), [
            'index' => 0, 'code' => $champ->ecran->code, 'libelle' => $champ->ecran->libelle, 'ecran' => $champ->ecran,
        ])) {
            return response()->json(['error' => 'Vous n’avez pas accès à cet écran.'], 403);
        }

        $saisies = array_change_key_case($validated['champs_saisis'] ?? [], CASE_UPPER);

        try {
            $resultat = $this->recherches->rechercher($produit, $champ, $saisies);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($resultat);
    }

    public function index(Request $request): InertiaResponse
    {
        $filters = array_filter([
            'q' => $request->input('q'),
            'statut' => $request->input('statut'),
            'vue' => $request->input('vue'),
        ], static fn ($v) => $v !== null && $v !== '');

        $user = $request->user();
        $aTraiterIds = $this->operationIdsATraiter($user);

        $query = PodOperation::query()
            ->with(['produit:id,code,libelle', 'user:id,name'])
            ->orderByDesc('created_at');

        if (($filters['vue'] ?? null) === 'a_traiter') {
            $query->whereIn('id', $aTraiterIds === [] ? [0] : $aTraiterIds);
        }

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
            'etape_libelle' => $op->etape_libelle,
            'a_traiter' => in_array($op->id, $aTraiterIds, true),
            'user_name' => $op->user?->name,
            'created_at' => optional($op->created_at)->toIso8601String(),
            'show_url' => route('pod.operations.show', $op),
        ]);

        return Inertia::render('Pod/Operations/Index', [
            'operations' => $operations,
            'filters' => $filters,
            'aTraiterCount' => count($aTraiterIds),
        ]);
    }

    public function create(Request $request): InertiaResponse
    {
        $user = $request->user();

        $produits = PodProduit::query()
            ->with('ecrans')
            ->where('actif', true)
            ->whereIn('statut', [PodProduit::STATUT_VALIDE, PodProduit::STATUT_PRODUCTION])
            ->orderBy('code')
            ->get()
            ->filter(fn (PodProduit $p) => PodOperationCircuit::userCanActOnStep($user, PodOperationCircuit::steps($p)[0]))
            ->values();

        $selectedId = $request->integer('produit_id') ?: null;
        $selected = null;
        if ($selectedId) {
            $selected = PodProduit::query()
                ->with(['tranches', 'lignesComptables', 'ecrans', 'champs.ecran', 'constantes', 'scripts'])
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
            'produit' => $selected ? $this->produitPayload($selected, $user) : null,
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pod_produit_id' => ['required', 'integer', 'exists:pod_produits,id'],
            'compte_client' => ['required', 'string', 'max:50'],
            'montant_demande' => ['nullable', 'numeric', 'min:0'],
            'frais_saisi' => ['nullable', 'numeric', 'min:0'],
            'champs_saisis' => ['nullable', 'array'],
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
            'compte_client' => ['nullable', 'string', 'max:50'],
            'code_agence' => ['nullable', 'string', 'max:20'],
            'nom_client' => ['nullable', 'string', 'max:255'],
            'date_valeur' => ['nullable', 'date'],
            'montant_demande' => ['nullable', 'numeric', 'min:0'],
            'frais_saisi' => ['nullable', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:100'],
            'libelle' => ['nullable', 'string', 'max:1000'],
            'champs_saisis' => ['nullable', 'array'],
            'fichiers' => ['nullable', 'array'],
            'fichiers.*' => ['array'],
            'fichiers.*.*' => self::FICHIER_RULES,
        ]);

        $user = $request->user();
        $produit = PodProduit::query()
            ->with(['ecrans', 'champs.ecran', 'constantes', 'scripts', 'lignesComptables', 'tranches'])
            ->findOrFail((int) $validated['pod_produit_id']);

        try {
            PodProduitWorkflow::assertUsableEnOperation($produit);
        } catch (ValidationException $e) {
            return redirect()->back()->withInput()->withErrors($e->errors());
        }

        $steps = PodOperationCircuit::steps($produit);
        $premiere = $steps[0];
        if (! PodOperationCircuit::userCanActOnStep($user, $premiere)) {
            return redirect()->back()->withInput()->with(
                'error',
                'Seul le rôle « '.PodOperationCircuit::roleLabel($premiere['ecran']).' » peut initier ce produit.',
            );
        }

        $fichiers = $this->fichiersParCode($request);

        try {
            $champsPremiere = PodOperationCircuit::champsForStep($produit, $premiere);
            $champsSaisis = PodChampSaisie::validateChamps(
                $champsPremiere,
                $validated['champs_saisis'] ?? [],
                $fichiers,
            );
            $recherche = $this->recherches->appliquer($produit, $champsPremiere, $champsSaisis);
        } catch (ValidationException $e) {
            return redirect()->back()->withInput()->withErrors($e->errors());
        }

        $champsSaisis = $recherche['saisies'];
        $validated = array_merge($validated, $recherche['systeme']);

        if (trim((string) ($validated['compte_client'] ?? '')) === '' && $produit->mode_frais !== PodProduit::MODE_GRATUIT) {
            return redirect()->back()->withInput()->withErrors(['compte_client' => 'Le compte client est obligatoire.']);
        }

        try {
            $calc = PodFraisCalculator::compute($produit, [...$validated, 'champs_saisis' => $champsSaisis]);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withInput()->withErrors(['calcul' => $e->getMessage()]);
        }

        if (! $calc['equilibre']) {
            return redirect()->back()->withInput()->withErrors([
                'calcul' => 'Schéma déséquilibré (débit '.$calc['total_debit'].' ≠ crédit '.$calc['total_credit'].').',
            ]);
        }

        $constantes = PodConstanteResolver::resolveAll($produit, [
            'montant_demande' => $validated['montant_demande'] ?? null,
            'frais_ht' => $calc['frais_ht'],
            'taux_taf' => $calc['taux_taf'],
            'champs_saisis' => $champsSaisis,
        ], false);

        $operation = DB::transaction(function () use ($validated, $produit, $calc, $champsSaisis, $constantes, $steps, $premiere, $fichiers, $user) {
            $operation = new PodOperation([
                'pod_produit_id' => $produit->id,
                'user_id' => $user->id,
                'reference' => $validated['reference'] ?? null,
                'compte_client' => $validated['compte_client'] ?? null,
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
                    'constantes' => $constantes,
                    'solde_apres_frais' => $this->soldeApresFrais($champsSaisis, (float) $calc['total_client']),
                ],
                'champs_saisis' => $champsSaisis,
                'statut' => PodOperation::STATUT_EN_COURS,
            ]);
            PodOperationCircuit::moveTo($operation, $steps, 1);
            $operation->save();

            foreach ($calc['lignes'] as $order => $ligne) {
                PodOperationLigne::create([
                    'pod_operation_id' => $operation->id,
                    'sort_order' => $order,
                    ...$ligne,
                ]);
            }

            $this->storePieces($operation, $premiere['index'], $fichiers, $user);
            PodOperationCircuit::log($operation, PodOperationEtape::ACTION_SOUMISE, $premiere, $user);

            return $operation;
        });

        return redirect()
            ->route('pod.operations.show', $operation)
            ->with('success', 'Demande soumise : en attente de « '.$operation->etape_libelle.' ».');
    }

    public function show(Request $request, PodOperation $operation): InertiaResponse
    {
        $operation->load([
            'produit.ecrans',
            'produit.champs.ecran',
            'user:id,name',
            'validatedBy:id,name',
            'lignes',
            'etapes.user:id,name',
            'pieces.user:id,name',
        ]);

        $user = $request->user();
        $produit = $operation->produit;
        $steps = $produit ? PodOperationCircuit::steps($produit) : [];
        $courant = $steps !== [] ? PodOperationCircuit::currentIndex($operation, $steps) : 0;
        $blockReason = $steps !== [] ? PodOperationCircuit::blockReason($user, $operation, $steps) : 'Produit introuvable.';
        $saisies = $operation->champs_saisis ?? [];

        $piecesParChamp = $operation->pieces->groupBy('champ_code');

        $circuit = collect($steps)->map(function (array $step) use ($operation, $courant, $produit, $saisies, $piecesParChamp) {
            $derniere = $operation->etapes
                ->where('etape_index', $step['index'])
                ->whereIn('action', [PodOperationEtape::ACTION_SOUMISE, PodOperationEtape::ACTION_VALIDEE])
                ->last();

            $etat = match (true) {
                $operation->statut === PodOperation::STATUT_VALIDE => 'fait',
                $step['index'] < $courant => 'fait',
                $step['index'] > $courant => 'a_venir',
                $operation->statut === PodOperation::STATUT_SUSPENDUE => 'suspendu',
                $operation->statut === PodOperation::STATUT_REJETE => 'rejete',
                $operation->statut === PodOperation::STATUT_ANNULE => 'annule',
                default => 'courant',
            };

            $champs = $produit
                ? PodOperationCircuit::champsForStep($produit, $step)->map(fn (PodProduitChamp $c) => [
                    'code' => $c->code,
                    'libelle' => $c->libelle,
                    'type' => $c->type,
                    'valeur' => $this->valeurAffichee($c, $saisies[$c->code] ?? null),
                    'pieces' => $c->type === PodProduitChamp::TYPE_FICHIER
                        ? $this->piecesPayload($operation, $piecesParChamp->get($c->code, collect()))
                        : [],
                ])->values()
                : collect();

            return [
                'index' => $step['index'],
                'code' => $step['code'],
                'libelle' => $step['libelle'],
                'role' => PodOperationCircuit::roleLabel($step['ecran']),
                'etat' => $etat,
                'acteur' => $derniere?->user?->name,
                'date' => optional($derniere?->created_at)->toIso8601String(),
                'champs' => $etat === 'fait' || $step['index'] === 0 ? $champs : [],
            ];
        })->values();

        $peutAgir = $blockReason === null;
        $estCreateur = $user && (int) $operation->user_id === (int) $user->id;
        $peutReprendre = $operation->statut === PodOperation::STATUT_SUSPENDUE
            && ($estCreateur || ModuleAccess::isAdminUser($user));

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
                'etape_libelle' => $operation->etape_libelle,
                'motif_annulation' => $operation->motif_annulation,
                'motif_decision' => $operation->motif_decision,
                'validated_at' => optional($operation->validated_at)->toIso8601String(),
                'validated_by_name' => $operation->validatedBy?->name,
                'calcul_detail' => $operation->calcul_detail,
                'user_name' => $operation->user?->name,
                'created_at' => optional($operation->created_at)->toIso8601String(),
                'pdf_url' => route('pod.operations.pdf', $operation),
                'produit' => [
                    'id' => $produit?->id,
                    'code' => $produit?->code,
                    'libelle' => $produit?->libelle,
                    'mode_frais' => $produit?->mode_frais,
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
            'circuit' => $circuit,
            'historique' => $operation->etapes->map(fn (PodOperationEtape $e) => [
                'id' => $e->id,
                'action' => $e->action,
                'ecran_libelle' => $e->ecran_libelle,
                'user_name' => $e->user?->name,
                'commentaire' => $e->commentaire,
                'created_at' => optional($e->created_at)->toIso8601String(),
            ])->values(),
            'action' => [
                'peut_agir' => $peutAgir,
                'motif_blocage' => $operation->statut === PodOperation::STATUT_EN_COURS ? $blockReason : null,
                'etape' => $steps[$courant]['libelle'] ?? null,
                'dernier' => $courant === count($steps) - 1,
                'champs' => $peutAgir && $produit
                    ? $this->champsPayload(PodOperationCircuit::champsForStep($produit, $steps[$courant]), $saisies, $this->nomsPiecesExistantes($operation))
                    : [],
            ],
            'reprise' => [
                'peut_reprendre' => $peutReprendre,
                'champs' => $peutReprendre && $produit
                    ? $this->champsPayload(PodOperationCircuit::champsForStep($produit, $steps[0]), $saisies, $this->nomsPiecesExistantes($operation))
                    : [],
            ],
            'peut_annuler' => in_array($operation->statut, [PodOperation::STATUT_EN_COURS, PodOperation::STATUT_SUSPENDUE], true)
                && ($estCreateur || ModuleAccess::isAdminUser($user)),
        ]);
    }

    public function valider(Request $request, PodOperation $operation): RedirectResponse
    {
        $validated = $request->validate([
            'commentaire' => ['nullable', 'string', 'max:2000'],
            'champs_saisis' => ['nullable', 'array'],
            'fichiers' => ['nullable', 'array'],
            'fichiers.*' => ['array'],
            'fichiers.*.*' => self::FICHIER_RULES,
        ]);

        $user = $request->user();
        $operation->load(['produit.ecrans', 'produit.champs.ecran', 'produit.constantes', 'produit.scripts', 'produit.lignesComptables', 'etapes', 'pieces', 'lignes']);
        $produit = $operation->produit;
        $steps = PodOperationCircuit::steps($produit);

        if ($reason = PodOperationCircuit::blockReason($user, $operation, $steps)) {
            return redirect()->back()->with('error', $reason);
        }

        $index = PodOperationCircuit::currentIndex($operation, $steps);
        $step = $steps[$index];
        $dernier = $index === count($steps) - 1;
        $fichiers = $this->fichiersParCode($request);

        try {
            $champsEtape = PodOperationCircuit::champsForStep($produit, $step);
            $nouveaux = PodChampSaisie::validateChamps(
                $champsEtape,
                $validated['champs_saisis'] ?? [],
                $fichiers,
                $this->nomsPiecesExistantes($operation),
            );
            $recherche = $this->recherches->appliquer(
                $produit,
                $champsEtape,
                array_merge($operation->champs_saisis ?? [], $nouveaux),
            );
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        $champsSaisis = $recherche['saisies'];
        $detail = $operation->calcul_detail ?? [];

        if ($dernier) {
            $placeholder = $operation->lignes->contains(fn ($l) => (bool) preg_match('/X{3,}/i', (string) $l->compte));
            if ($placeholder) {
                return redirect()->back()->with('error', 'Validation finale impossible : compte placeholder dans les écritures.');
            }

            try {
                $detail['constantes'] = PodConstanteResolver::resolveAll($produit, [
                    'montant_demande' => $operation->montant_demande !== null ? (float) $operation->montant_demande : null,
                    'frais_ht' => (float) $operation->frais_ht,
                    'taux_taf' => (float) $operation->taux_taf,
                    'champs_saisis' => $champsSaisis,
                ]);
            } catch (\InvalidArgumentException $e) {
                return redirect()->back()->with('error', $e->getMessage());
            }
        }

        DB::transaction(function () use ($operation, $steps, $step, $index, $dernier, $champsSaisis, $detail, $fichiers, $user, $validated) {
            $this->storePieces($operation, $index, $fichiers, $user);
            PodOperationCircuit::log($operation, PodOperationEtape::ACTION_VALIDEE, $step, $user, $validated['commentaire'] ?? null);

            $operation->champs_saisis = $champsSaisis;
            $operation->calcul_detail = $detail;
            $operation->motif_decision = null;

            if ($dernier) {
                $operation->statut = PodOperation::STATUT_VALIDE;
                $operation->validated_by_user_id = $user->id;
                $operation->validated_at = now();
                $operation->etape_libelle = null;
            } else {
                PodOperationCircuit::moveTo($operation, $steps, $index + 1);
            }

            $operation->save();
        });

        return redirect()->back()->with(
            'success',
            $dernier ? 'Dossier validé : circuit terminé.' : 'Étape validée : en attente de « '.$operation->etape_libelle.' ».',
        );
    }

    public function suspendre(Request $request, PodOperation $operation): RedirectResponse
    {
        $validated = $request->validate([
            'motif' => ['required', 'string', 'max:2000'],
        ], ['motif.required' => 'Le motif de suspension est obligatoire.']);

        $user = $request->user();
        $operation->load(['produit.ecrans', 'etapes']);
        $steps = PodOperationCircuit::steps($operation->produit);

        if ($reason = PodOperationCircuit::blockReason($user, $operation, $steps)) {
            return redirect()->back()->with('error', $reason);
        }

        $step = $steps[PodOperationCircuit::currentIndex($operation, $steps)];

        DB::transaction(function () use ($operation, $step, $user, $validated) {
            PodOperationCircuit::log($operation, PodOperationEtape::ACTION_SUSPENDUE, $step, $user, $validated['motif']);
            $operation->update([
                'statut' => PodOperation::STATUT_SUSPENDUE,
                'motif_decision' => $validated['motif'],
            ]);
        });

        return redirect()->back()->with('success', 'Dossier suspendu : l’initiateur doit le compléter.');
    }

    public function reprendre(Request $request, PodOperation $operation): RedirectResponse
    {
        $validated = $request->validate([
            'commentaire' => ['nullable', 'string', 'max:2000'],
            'champs_saisis' => ['nullable', 'array'],
            'fichiers' => ['nullable', 'array'],
            'fichiers.*' => ['array'],
            'fichiers.*.*' => self::FICHIER_RULES,
        ]);

        $user = $request->user();
        if ($operation->statut !== PodOperation::STATUT_SUSPENDUE) {
            return redirect()->back()->with('error', 'Seul un dossier suspendu peut être repris.');
        }
        if ((int) $operation->user_id !== (int) $user->id && ! ModuleAccess::isAdminUser($user)) {
            return redirect()->back()->with('error', 'Seul l’initiateur du dossier peut le reprendre.');
        }

        $operation->load(['produit.ecrans', 'produit.champs.ecran', 'pieces']);
        $steps = PodOperationCircuit::steps($operation->produit);
        $premiere = $steps[0];
        $fichiers = $this->fichiersParCode($request);

        try {
            $champsPremiere = PodOperationCircuit::champsForStep($operation->produit, $premiere);
            $nouveaux = PodChampSaisie::validateChamps(
                $champsPremiere,
                $validated['champs_saisis'] ?? [],
                $fichiers,
                $this->nomsPiecesExistantes($operation),
            );
            $nouveaux = $this->recherches->appliquer(
                $operation->produit,
                $champsPremiere,
                array_merge($operation->champs_saisis ?? [], $nouveaux),
            )['saisies'];
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        DB::transaction(function () use ($operation, $premiere, $nouveaux, $fichiers, $user, $validated) {
            $this->storePieces($operation, $premiere['index'], $fichiers, $user);
            PodOperationCircuit::log($operation, PodOperationEtape::ACTION_REPRISE, $premiere, $user, $validated['commentaire'] ?? null);
            $operation->update([
                'champs_saisis' => array_merge($operation->champs_saisis ?? [], $nouveaux),
                'statut' => PodOperation::STATUT_EN_COURS,
                'motif_decision' => null,
            ]);
        });

        return redirect()->back()->with('success', 'Dossier repris : de nouveau en attente de « '.$operation->etape_libelle.' ».');
    }

    public function rejeter(Request $request, PodOperation $operation): RedirectResponse
    {
        $validated = $request->validate([
            'motif' => ['required', 'string', 'max:2000'],
        ], ['motif.required' => 'Le motif de rejet est obligatoire.']);

        $user = $request->user();
        $operation->load(['produit.ecrans', 'etapes']);
        $steps = PodOperationCircuit::steps($operation->produit);

        if ($reason = PodOperationCircuit::blockReason($user, $operation, $steps)) {
            return redirect()->back()->with('error', $reason);
        }

        $step = $steps[PodOperationCircuit::currentIndex($operation, $steps)];

        DB::transaction(function () use ($operation, $step, $user, $validated) {
            PodOperationCircuit::log($operation, PodOperationEtape::ACTION_REJETEE, $step, $user, $validated['motif']);
            $operation->update([
                'statut' => PodOperation::STATUT_REJETE,
                'motif_decision' => $validated['motif'],
            ]);
        });

        return redirect()->back()->with('success', 'Dossier rejeté.');
    }

    public function annuler(Request $request, PodOperation $operation): RedirectResponse
    {
        $user = $request->user();
        if (! in_array($operation->statut, [PodOperation::STATUT_EN_COURS, PodOperation::STATUT_SUSPENDUE, PodOperation::STATUT_BROUILLON], true)) {
            return redirect()->back()->with('error', 'Ce dossier ne peut plus être annulé.');
        }
        if ((int) $operation->user_id !== (int) $user->id && ! ModuleAccess::isAdminUser($user)) {
            return redirect()->back()->with('error', 'Seul l’initiateur du dossier peut l’annuler.');
        }

        $validated = $request->validate([
            'motif_annulation' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($operation, $user, $validated) {
            PodOperationCircuit::log($operation, PodOperationEtape::ACTION_ANNULEE, null, $user, $validated['motif_annulation'] ?? null);
            $operation->update([
                'statut' => PodOperation::STATUT_ANNULE,
                'motif_annulation' => $validated['motif_annulation'] ?? null,
            ]);
        });

        return redirect()->back()->with('success', 'Opération annulée.');
    }

    public function piece(PodOperation $operation, PodOperationPiece $piece): StreamedResponse
    {
        abort_unless((int) $piece->pod_operation_id === (int) $operation->id, 404);
        abort_unless(Storage::disk('local')->exists($piece->path), 404);

        return Storage::disk('local')->download($piece->path, $piece->original_name);
    }

    public function pdf(PodOperation $operation): Response
    {
        $operation->load(['produit', 'user:id,name', 'validatedBy:id,name', 'lignes']);

        $pdf = Pdf::loadView('pod.operation-pdf', [
            'operation' => $operation,
        ])->setPaper('a4');

        return $pdf->download('operation-pod-'.$operation->id.'.pdf');
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
     * @return list<int>
     */
    private function operationIdsATraiter(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return PodOperation::query()
            ->with(['produit.ecrans', 'etapes'])
            ->where('statut', PodOperation::STATUT_EN_COURS)
            ->latest()
            ->limit(500)
            ->get()
            ->filter(fn (PodOperation $op) => $op->produit
                && PodOperationCircuit::blockReason($user, $op, PodOperationCircuit::steps($op->produit)) === null)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array<string, list<UploadedFile>>
     */
    private function fichiersParCode(Request $request): array
    {
        $out = [];
        foreach ((array) $request->file('fichiers', []) as $code => $files) {
            $out[strtoupper((string) $code)] = array_values(array_filter(
                is_array($files) ? $files : [$files],
                fn ($f) => $f instanceof UploadedFile,
            ));
        }

        return $out;
    }

    /**
     * @param  array<string, list<UploadedFile>>  $fichiers
     */
    private function storePieces(PodOperation $operation, int $etapeIndex, array $fichiers, User $user): void
    {
        foreach ($fichiers as $code => $files) {
            foreach ($files as $file) {
                $path = $file->store('pod/operations/'.$operation->id, 'local');
                PodOperationPiece::create([
                    'pod_operation_id' => $operation->id,
                    'champ_code' => $code,
                    'etape_index' => $etapeIndex,
                    'user_id' => $user->id,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime' => $file->getClientMimeType(),
                    'size' => (int) $file->getSize(),
                ]);
            }
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function nomsPiecesExistantes(PodOperation $operation): array
    {
        return $operation->pieces
            ->groupBy('champ_code')
            ->map(fn (Collection $pieces) => $pieces->pluck('original_name')->values()->all())
            ->all();
    }

    /**
     * @param  Collection<int, PodOperationPiece>  $pieces
     * @return list<array<string, mixed>>
     */
    private function piecesPayload(PodOperation $operation, Collection $pieces): array
    {
        return $pieces->map(fn (PodOperationPiece $p) => [
            'id' => $p->id,
            'nom' => $p->original_name,
            'taille' => $p->size,
            'user_name' => $p->user?->name,
            'url' => route('pod.operations.pieces', [$operation, $p]),
        ])->values()->all();
    }

    /**
     * Solde net − (encours global dû + frais TTC), pour les produits qui récupèrent ces deux montants.
     * Un résultat négatif est le montant que le client doit compléter.
     *
     * @param  array<string, mixed>  $saisies
     */
    private function soldeApresFrais(array $saisies, float $fraisTtc): ?float
    {
        if (! isset($saisies['SOLDE_NET']) || ! is_numeric($saisies['SOLDE_NET'])) {
            return null;
        }

        return round((float) $saisies['SOLDE_NET'] - ((float) ($saisies['ENCOURS_GLOBAL_DUE'] ?? 0) + $fraisTtc), 2);
    }

    private function valeurAffichee(PodProduitChamp $champ, mixed $valeur): mixed
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }

        return match ($champ->type) {
            PodProduitChamp::TYPE_LISTE => collect($champ->valeursListeActives())->firstWhere('code', (string) $valeur)['libelle'] ?? $valeur,
            PodProduitChamp::TYPE_TABLE => collect($champ->valeursTableActives())->firstWhere('code', (string) $valeur)['libelle'] ?? $valeur,
            PodProduitChamp::TYPE_NUMERIQUE => is_numeric($valeur)
                ? number_format((float) $valeur, (int) (($champ->options ?? [])['decimales'] ?? 2), ',', ' ')
                : $valeur,
            PodProduitChamp::TYPE_DATE => \Illuminate\Support\Carbon::parse((string) $valeur)->format('d/m/Y'),
            default => $valeur,
        };
    }

    /**
     * @param  Collection<int, PodProduitChamp>  $champs
     * @param  array<string, mixed>  $saisies
     * @return list<array<string, mixed>>
     */
    private function champsPayload(Collection $champs, array $saisies = [], array $existants = []): array
    {
        return $champs->map(fn (PodProduitChamp $c) => [
            'pieces_existantes' => $existants[$c->code] ?? [],
            'code' => $c->code,
            'libelle' => $c->libelle,
            'type' => $c->type,
            'obligatoire' => $c->obligatoire,
            'visible' => $c->visible,
            'gris' => $c->gris,
            'valeur_defaut' => $c->valeur_defaut,
            'options' => $c->options ?? [],
            'ecran_code' => $c->ecran?->code,
            'valeur' => $c->type === PodProduitChamp::TYPE_FICHIER ? null : ($saisies[$c->code] ?? $c->valeur_defaut),
            'valeurs' => match ($c->type) {
                PodProduitChamp::TYPE_LISTE => $c->valeursListeActives(),
                PodProduitChamp::TYPE_TABLE => $c->valeursTableActives(),
                default => [],
            },
        ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function produitPayload(PodProduit $p, ?User $user = null): array
    {
        $steps = PodOperationCircuit::steps($p);
        $premiere = $steps[0];

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
            'bareme_champ' => $p->bareme_champ,
            'taxe_champ' => $p->taxe_champ,
            'peut_initier' => PodOperationCircuit::userCanActOnStep($user, $premiere),
            'tranches' => $p->tranches->map(fn ($t) => [
                'libelle' => $t->libelle,
                'critere' => $t->critere,
                'montant_min' => $t->montant_min,
                'montant_max' => $t->montant_max,
                'frais_fixe' => $t->frais_fixe,
                'taux' => $t->taux,
            ])->values(),
            'circuit' => collect($steps)->map(fn (array $s) => [
                'index' => $s['index'],
                'code' => $s['code'],
                'libelle' => $s['libelle'],
                'role' => PodOperationCircuit::roleLabel($s['ecran']),
            ])->values(),
            'ecrans' => $premiere['ecran'] ? [[
                'id' => $premiere['ecran']->id,
                'code' => $premiere['ecran']->code,
                'libelle' => $premiere['ecran']->libelle,
                'description' => $premiere['ecran']->description,
                'profils' => $premiere['ecran']->profils ?? [],
            ]] : [],
            'champs' => $this->champsPayload(PodOperationCircuit::champsForStep($p, $premiere)),
        ];
    }
}
