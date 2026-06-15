<?php

namespace App\Http\Controllers;

use App\Models\Agence;
use App\Models\FicheIntegration;
use App\Models\OdClasseur;
use App\Models\OdClasseurPiece;
use App\Services\Integrations\EcritureComptableImportApiClient;
use App\Support\OdArchivage;
use App\Support\OdIntegrationCsv;
use App\Support\OdIntegrationCsvTemplate;
use App\Support\OdSimpleIntegrationCsv;
use App\Support\OdManualIntegrationCsv;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationDiverseController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('operations-diverses.piece-comptable');
    }

    public function pieceComptable(EcritureComptableImportApiClient $importApi): InertiaResponse
    {
        return Inertia::render('OperationsDiverses/PieceComptableNouveau', [
            'comptableImportApiConfigured' => $importApi->isConfigured(),
            'templateCsvUrl' => route('operations-diverses.piece-comptable.template-csv'),
        ]);
    }

    public function pieceComptableTemplateCsv(): StreamedResponse
    {
        $contents = OdIntegrationCsvTemplate::build();

        return response()->streamDownload(
            static function () use ($contents): void {
                echo $contents;
            },
            'modele-integration-od.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    public function pieceComptableManuelle(EcritureComptableImportApiClient $importApi): InertiaResponse
    {
        return Inertia::render('OperationsDiverses/PieceComptableManuelle', [
            'agences' => Agence::query()->orderBy('nom')->get(['id', 'code', 'nom']),
            'codesOperation' => FicheIntegration::query()
                ->whereNotNull('code_operation')
                ->distinct()
                ->orderBy('code_operation')
                ->pluck('code_operation')
                ->map(fn ($c) => (string) $c)
                ->values(),
            'comptableImportApiConfigured' => $importApi->isConfigured(),
        ]);
    }

    public function pieceComptableStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'numero_batch' => ['required', 'string', 'max:100'],
            'date_valeur' => ['required', 'date'],
            'nom_classeur' => ['required', 'string', 'max:255'],
            'fichier_integration' => ['required', 'file', 'mimes:csv,txt', 'max:15360'],
            'justificatifs' => ['required', 'array', 'min:1'],
            'justificatifs.*.description' => ['required', 'string', 'max:1000'],
            'justificatifs.*.file' => $this->justificatifFileRules(),
        ]);

        $integFile = $request->file('fichier_integration');
        $normalized = OdSimpleIntegrationCsv::normalizeUpload(
            file_get_contents($integFile->getRealPath()) ?: null,
            $validated['numero_batch'],
            $validated['date_valeur'],
            auth()->user(),
        );
        if ($normalized['error'] !== null) {
            return redirect()->back()->withInput()->withErrors([
                'fichier_integration' => $normalized['error'],
            ]);
        }

        $flexContents = $normalized['contents'];

        $classeur = $this->persistClasseurBrouillon(
            $request,
            [
                'nom_classeur' => $validated['nom_classeur'],
                'date_valeur' => $validated['date_valeur'],
                'numero_batch' => $validated['numero_batch'],
            ],
            function (string $baseDir) use ($flexContents) {
                $path = $baseDir.'/integration/integration.csv';
                Storage::disk('local')->put($path, $flexContents);

                return $path;
            },
            $integFile->getClientOriginalName(),
            $validated['justificatifs'],
        );

        return $this->finalizeClasseurCreation($classeur);
    }

    public function pieceComptableManuelleStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'numero_batch' => ['required', 'string', 'max:100'],
            'nom_classeur' => ['required', 'string', 'max:255'],
            'lignes' => ['required', 'array', 'min:1'],
            'lignes.*.date_de_valeur' => ['required', 'string', 'max:20'],
            'lignes.*.code_agence' => ['required', 'string', 'max:50'],
            'lignes.*.no_compte' => ['required', 'string', 'max:100'],
            'lignes.*.related_account' => ['nullable', 'string', 'max:100'],
            'lignes.*.montant' => ['required', 'numeric', 'min:0.01'],
            'lignes.*.sens' => ['required', 'string', 'in:C,D,c,d'],
            'lignes.*.libelle_ecriture' => ['required', 'string', 'max:500'],
            'lignes.*.code_operation' => ['required', 'string', 'max:50'],
            'justificatifs' => ['required', 'array', 'min:1'],
            'justificatifs.*.description' => ['required', 'string', 'max:1000'],
            'justificatifs.*.file' => $this->justificatifFileRules(),
        ]);

        $lineErrors = OdManualIntegrationCsv::validateLines($validated['lignes']);
        if ($lineErrors !== []) {
            return redirect()->back()->withInput()->withErrors($lineErrors);
        }

        $dateValeur = OdManualIntegrationCsv::classeurDateValeur($validated['lignes']);
        if ($dateValeur === null) {
            return redirect()->back()->withInput()->withErrors([
                'lignes' => 'Les dates des lignes OD sont invalides.',
            ]);
        }

        $user = auth()->user();
        $csvContents = OdManualIntegrationCsv::build(
            $validated['numero_batch'],
            $validated['lignes'],
            $user,
        );

        $classeur = $this->persistClasseurBrouillon(
            $request,
            [
                'nom_classeur' => $validated['nom_classeur'],
                'date_valeur' => $dateValeur,
                'numero_batch' => $validated['numero_batch'],
            ],
            function (string $baseDir) use ($csvContents, $validated) {
                $path = $baseDir.'/integration/integration-manuelle.csv';
                Storage::disk('local')->put($path, $csvContents);
                Storage::disk('local')->put(
                    $baseDir.'/integration/manual-lines.json',
                    json_encode($validated['lignes'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '[]'
                );

                return $path;
            },
            'integration-manuelle.csv',
            $validated['justificatifs'],
        );

        return $this->finalizeClasseurCreation($classeur);
    }

    public function pieceComptableModifier(OdClasseur $classeur, EcritureComptableImportApiClient $importApi): InertiaResponse
    {
        $this->authorizeClasseur($classeur);
        abort_unless(! $classeur->isIntegre(), 403, 'Impossible de modifier une intégration validée.');

        $classeur->load(['pieces']);

        if ($this->isManuelleIntegration($classeur)) {
            $lignes = array_map(static function (array $ligne): array {
                return [
                    'date_de_valeur' => (string) ($ligne['date_de_valeur'] ?? ''),
                    'code_agence' => (string) ($ligne['code_agence'] ?? ''),
                    'no_compte' => (string) ($ligne['no_compte'] ?? ''),
                    'related_account' => (string) ($ligne['related_account'] ?? ''),
                    'montant' => (string) ($ligne['montant'] ?? ''),
                    'sens' => strtoupper((string) ($ligne['sens'] ?? '')),
                    'libelle_ecriture' => (string) ($ligne['libelle_ecriture'] ?? ''),
                    'code_operation' => (string) ($ligne['code_operation'] ?? ''),
                ];
            }, $this->manualLinesMeta($classeur));

            return Inertia::render('OperationsDiverses/PieceComptableManuelle', [
                'agences' => Agence::query()->orderBy('nom')->get(['id', 'code', 'nom']),
                'codesOperation' => FicheIntegration::query()
                    ->whereNotNull('code_operation')
                    ->distinct()
                    ->orderBy('code_operation')
                    ->pluck('code_operation')
                    ->map(fn ($c) => (string) $c)
                    ->values(),
                'comptableImportApiConfigured' => $importApi->isConfigured(),
                'editing' => true,
                'classeur' => $this->editPayloadManuelle($classeur, $lignes),
            ]);
        }

        return Inertia::render('OperationsDiverses/PieceComptableNouveau', [
            'comptableImportApiConfigured' => $importApi->isConfigured(),
            'templateCsvUrl' => route('operations-diverses.piece-comptable.template-csv'),
            'editing' => true,
            'classeur' => $this->editPayloadAutomatique($classeur),
        ]);
    }

    public function pieceComptableUpdate(Request $request, OdClasseur $classeur): RedirectResponse
    {
        $this->authorizeClasseur($classeur);
        abort_unless(! $classeur->isIntegre(), 403, 'Impossible de modifier une intégration validée.');

        $validated = $request->validate([
            'numero_batch' => ['required', 'string', 'max:100'],
            'date_valeur' => ['required', 'date'],
            'nom_classeur' => ['required', 'string', 'max:255'],
            'fichier_integration' => ['nullable', 'file', 'mimes:csv,txt', 'max:15360'],
        ]);

        $batchOrDateChanged = $classeur->numero_batch !== $validated['numero_batch']
            || optional($classeur->date_valeur)->toDateString() !== $validated['date_valeur'];

        $classeur->update([
            'nom_classeur' => $validated['nom_classeur'],
            'date_valeur' => $validated['date_valeur'],
            'numero_batch' => $validated['numero_batch'],
            'numero_piece' => $validated['numero_batch'],
        ]);

        if ($request->hasFile('fichier_integration')) {
            $integFile = $request->file('fichier_integration');
            $normalized = OdSimpleIntegrationCsv::normalizeUpload(
                file_get_contents($integFile->getRealPath()) ?: null,
                $validated['numero_batch'],
                $validated['date_valeur'],
                $classeur->user ?? auth()->user(),
            );
            if ($normalized['error'] !== null) {
                return redirect()->back()->withInput()->withErrors([
                    'fichier_integration' => $normalized['error'],
                ]);
            }

            $baseDir = 'od/classeurs/'.$classeur->id;

            if ($classeur->fichier_integration_path) {
                Storage::disk('local')->delete($classeur->fichier_integration_path);
            }

            $path = $baseDir.'/integration/integration.csv';
            Storage::disk('local')->put($path, $normalized['contents']);
            $classeur->update([
                'fichier_integration_path' => $path,
                'fichier_integration_original_name' => $integFile->getClientOriginalName(),
            ]);
        } elseif ($batchOrDateChanged) {
            $this->refreshStoredIntegrationCsv(
                $classeur,
                $validated['numero_batch'],
                $validated['date_valeur'],
            );
        }

        $this->appendJustificatifsFromRequest($request, $classeur);

        return $this->finalizeClasseurUpdate($classeur);
    }

    public function pieceComptableManuelleUpdate(Request $request, OdClasseur $classeur): RedirectResponse
    {
        $this->authorizeClasseur($classeur);
        abort_unless(! $classeur->isIntegre(), 403, 'Impossible de modifier une intégration validée.');
        abort_unless($this->isManuelleIntegration($classeur), 403, 'Cette intégration n’est pas une saisie manuelle.');

        $validated = $request->validate([
            'numero_batch' => ['required', 'string', 'max:100'],
            'nom_classeur' => ['required', 'string', 'max:255'],
            'lignes' => ['required', 'array', 'min:1'],
            'lignes.*.date_de_valeur' => ['required', 'string', 'max:20'],
            'lignes.*.code_agence' => ['required', 'string', 'max:50'],
            'lignes.*.no_compte' => ['required', 'string', 'max:100'],
            'lignes.*.related_account' => ['nullable', 'string', 'max:100'],
            'lignes.*.montant' => ['required', 'numeric', 'min:0.01'],
            'lignes.*.sens' => ['required', 'string', 'in:C,D,c,d'],
            'lignes.*.libelle_ecriture' => ['required', 'string', 'max:500'],
            'lignes.*.code_operation' => ['required', 'string', 'max:50'],
        ]);

        $lineErrors = OdManualIntegrationCsv::validateLines($validated['lignes']);
        if ($lineErrors !== []) {
            return redirect()->back()->withInput()->withErrors($lineErrors);
        }

        $dateValeur = OdManualIntegrationCsv::classeurDateValeur($validated['lignes']);
        if ($dateValeur === null) {
            return redirect()->back()->withInput()->withErrors([
                'lignes' => 'Les dates des lignes OD sont invalides.',
            ]);
        }

        $user = $classeur->user ?? auth()->user();
        $csvContents = OdManualIntegrationCsv::build(
            $validated['numero_batch'],
            $validated['lignes'],
            $user,
        );

        $baseDir = 'od/classeurs/'.$classeur->id;
        $path = $baseDir.'/integration/integration-manuelle.csv';
        Storage::disk('local')->put($path, $csvContents);
        Storage::disk('local')->put(
            $baseDir.'/integration/manual-lines.json',
            json_encode($validated['lignes'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '[]'
        );

        $classeur->update([
            'nom_classeur' => $validated['nom_classeur'],
            'date_valeur' => $dateValeur,
            'numero_batch' => $validated['numero_batch'],
            'numero_piece' => $validated['numero_batch'],
            'fichier_integration_path' => $path,
            'fichier_integration_original_name' => 'integration-manuelle.csv',
        ]);

        $this->appendJustificatifsFromRequest($request, $classeur);

        return $this->finalizeClasseurUpdate($classeur);
    }

    public function pieceComptableResume(OdClasseur $classeur, EcritureComptableImportApiClient $importApi): InertiaResponse
    {
        $this->authorizeClasseur($classeur);
        $classeur->load(['user', 'pieces']);

        $parsed = $this->parseIntegration($classeur);

        return Inertia::render('OperationsDiverses/PieceComptableResume', [
            'classeur' => $this->classeurResumePayload($classeur),
            'apercu' => $this->apercuPayload($parsed),
            'comptableImportApiConfigured' => $importApi->isConfigured(),
        ]);
    }

    public function pieceComptableValider(OdClasseur $classeur, EcritureComptableImportApiClient $importApi): RedirectResponse
    {
        $this->authorizeClasseur($classeur);

        $redirect = redirect()->back()->withInput();

        if ($classeur->isIntegre()) {
            return $redirect->with('warning', 'Cette intégration a déjà été validée.');
        }

        $contents = $this->integrationContents($classeur);
        if ($contents === null) {
            return $redirect->with('error', 'Fichier d’intégration introuvable : impossible de valider.');
        }

        if (! $importApi->isConfigured()) {
            return $redirect->with('error', 'L’API de la plateforme n’est pas configurée : intégration impossible.');
        }

        $name = $classeur->fichier_integration_original_name
            ?: (string) config('services.ecritures_comptables_import.csv_filename', 'RQFT.csv');

        try {
            $response = $importApi->uploadFileContents($contents, $name);
        } catch (\Throwable $e) {
            report($e);

            return $redirect->with('error', 'Envoi vers la plateforme échoué : '.$e->getMessage());
        }

        if (! $response->successful()) {
            return $redirect->with(
                'error',
                'La plateforme a rejeté le fichier (HTTP '.$response->status().') : '.Str::limit($response->body(), 600)
            );
        }

        // Intégration réussie : on fige le classeur, on génère la pièce comptable et on l'archive du jour.
        $classeur->forceFill([
            'statut' => 'integre',
            'integrated_at' => now(),
            'archive_date' => now()->toDateString(),
            'archived_at' => now(),
            'integration_status_code' => $response->status(),
        ])->save();

        try {
            $this->genererPieceComptable($classeur->fresh(['user']));
        } catch (\Throwable $e) {
            report($e);

            return $redirect->with(
                'warning',
                'Intégration réussie et archivée, mais la génération du PDF de la pièce comptable a échoué : '.$e->getMessage()
            );
        }

        return redirect()
            ->route('operations-diverses.archivage')
            ->with(
                'success',
                'Intégration validée et transmise à la plateforme. Pièce comptable générée et archivée.'
            );
    }

    public function pieceComptablePdf(Request $request, OdClasseur $classeur): BinaryFileResponse|StreamedResponse
    {
        $this->authorizeClasseur($classeur);

        $classeur->load('user');

        if (! $classeur->piece_pdf_path || ! Storage::disk('local')->exists($classeur->piece_pdf_path)) {
            $this->genererPieceComptable($classeur);
            $classeur->refresh();
        }

        $filename = 'piece-comptable-'.Str::slug((string) $classeur->numero_piece, '_').'.pdf';
        $path = Storage::disk('local')->path($classeur->piece_pdf_path);

        if ($request->boolean('inline')) {
            return response()->file($path, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]);
        }

        return Storage::disk('local')->download($classeur->piece_pdf_path, $filename);
    }

    public function justificatifDownload(OdClasseur $classeur, OdClasseurPiece $piece): StreamedResponse
    {
        $this->authorizeClasseur($classeur);

        if ($piece->od_classeur_id !== $classeur->id || ! Storage::disk('local')->exists($piece->storage_path)) {
            abort(404);
        }

        return Storage::disk('local')->download($piece->storage_path, $piece->original_name);
    }

    public function justificatifPreview(OdClasseur $classeur, OdClasseurPiece $piece): BinaryFileResponse
    {
        $this->authorizeClasseur($classeur);

        if ($piece->od_classeur_id !== $classeur->id || ! Storage::disk('local')->exists($piece->storage_path)) {
            abort(404);
        }

        if (! $this->canPreviewFile($piece->original_name, $piece->mime_type)) {
            abort(415, 'Aperçu non disponible pour ce type de fichier.');
        }

        $mime = $piece->mime_type ?: (Storage::disk('local')->mimeType($piece->storage_path) ?: 'application/octet-stream');
        $filename = str_replace('"', '', $piece->original_name);

        return response()->file(Storage::disk('local')->path($piece->storage_path), [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    public function integrations(Request $request, EcritureComptableImportApiClient $importApi): InertiaResponse
    {
        $user = auth()->user();
        $canViewAllAgents = $this->voitTousLesClasseurs($user);

        $filters = array_filter([
            'q' => $request->input('q'),
            'nom_classeur' => $request->input('nom_classeur'),
            'numero_batch' => $request->input('numero_batch'),
            'user_id' => $request->input('user_id'),
        ], static fn ($v) => $v !== null && $v !== '');

        $query = OdClasseur::query()
            ->with(['user', 'pieces'])
            ->where('statut', 'brouillon');

        if (! $canViewAllAgents) {
            $query->where('user_id', $user->id);
        }

        if (! empty($filters['q'])) {
            $term = '%'.trim((string) $filters['q']).'%';
            $query->where(function ($w) use ($term) {
                $w->where('nom_classeur', 'like', $term)
                    ->orWhere('numero_batch', 'like', $term)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term));
            });
        }

        if (! empty($filters['nom_classeur'])) {
            $query->where('nom_classeur', 'like', '%'.trim((string) $filters['nom_classeur']).'%');
        }

        if (! empty($filters['numero_batch'])) {
            $query->where('numero_batch', 'like', '%'.trim((string) $filters['numero_batch']).'%');
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        $classeurs = $query
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (OdClasseur $c) => $this->integrationListPayload($c));

        $agents = OdClasseur::query()
            ->where('statut', 'brouillon')
            ->when(! $canViewAllAgents, fn ($q) => $q->where('user_id', $user->id))
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
            ->sortBy('name')
            ->values();

        return Inertia::render('OperationsDiverses/Integrations', [
            'classeurs' => $classeurs,
            'agents' => $agents,
            'filters' => $filters,
            'canViewAllAgents' => $canViewAllAgents,
            'comptableImportApiConfigured' => $importApi->isConfigured(),
        ]);
    }

    public function archivage(Request $request, EcritureComptableImportApiClient $importApi): InertiaResponse
    {
        $user = auth()->user();
        $canViewAllAgents = $this->voitTousLesClasseurs($user);

        $filters = OdArchivage::normalizeFilters($request->only([
            'q',
            'nom_classeur',
            'numero_batch',
            'user_id',
            'annee',
            'mois',
            'jour',
            'archive_du',
            'archive_au',
            'date_valeur_du',
            'date_valeur_au',
        ]));

        $query = OdClasseur::query()
            ->with(['user.roles', 'pieces'])
            ->where('statut', 'integre');

        if (! $canViewAllAgents) {
            $query->where('user_id', $user->id);
        }

        OdArchivage::applySearchFilters($query, $filters);

        $classeurs = $query
            ->orderByDesc('archive_date')
            ->orderByDesc('archived_at')
            ->get();

        $tree = OdArchivage::buildTree($classeurs, fn (OdClasseur $c) => $this->folderPayload($c));

        $agents = $classeurs
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'flex_id' => $u->flexComptaUserIdentifier(),
            ])
            ->sortBy('name')
            ->values();

        return Inertia::render('OperationsDiverses/Archivage', [
            'tree' => $tree,
            'agents' => $agents,
            'filters' => $filters,
            'searchActive' => OdArchivage::hasActiveSearch($filters),
            'searchResults' => OdArchivage::hasActiveSearch($filters)
                ? OdArchivage::flattenTreeForSearch($tree)
                : [],
            'canViewAllAgents' => $canViewAllAgents,
            'totalClasseurs' => $classeurs->count(),
            'comptableImportApiConfigured' => $importApi->isConfigured(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function classeurResumePayload(OdClasseur $classeur): array
    {
        return [
            'id' => $classeur->id,
            'numero_batch' => $classeur->numero_batch,
            'numero_piece' => $classeur->numero_piece,
            'nom_classeur' => $classeur->nom_classeur,
            'date_valeur' => optional($classeur->date_valeur)->toDateString(),
            'statut' => $classeur->statut,
            'integrated_at' => optional($classeur->integrated_at)->toIso8601String(),
            'user_name' => $classeur->user?->name,
            'fichier' => $classeur->fichier_integration_original_name,
            'modifier_url' => $classeur->isIntegre()
                ? null
                : route('operations-diverses.piece-comptable.modifier', $classeur),
            'pieces' => $this->piecesJustificativesPayload($classeur),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function editPayloadAutomatique(OdClasseur $classeur): array
    {
        return [
            'id' => $classeur->id,
            'numero_batch' => $classeur->numero_batch,
            'date_valeur' => optional($classeur->date_valeur)->toDateString(),
            'nom_classeur' => $classeur->nom_classeur,
            'fichier' => $classeur->fichier_integration_original_name,
            'resume_url' => route('operations-diverses.piece-comptable.resume', $classeur),
            'pieces' => $this->piecesJustificativesPayload($classeur),
        ];
    }

    /**
     * @param  list<array<string, string>>  $lignes
     * @return array<string, mixed>
     */
    private function editPayloadManuelle(OdClasseur $classeur, array $lignes): array
    {
        return [
            'id' => $classeur->id,
            'numero_batch' => $classeur->numero_batch,
            'nom_classeur' => $classeur->nom_classeur,
            'lignes' => $lignes,
            'resume_url' => route('operations-diverses.piece-comptable.resume', $classeur),
            'pieces' => $this->piecesJustificativesPayload($classeur),
        ];
    }

    /**
     * Justificatifs joints + pièce comptable PDF générée.
     *
     * @return \Illuminate\Support\Collection<int, array{id: int|string, description: string|null, original_name: string, url: string, is_piece_comptable: bool}>
     */
    private function piecesJustificativesPayload(OdClasseur $classeur): \Illuminate\Support\Collection
    {
        $items = $classeur->pieces->map(fn (OdClasseurPiece $p) => [
            'id' => $p->id,
            'description' => $p->description,
            'original_name' => $p->original_name,
            'url' => route('operations-diverses.justificatif.download', [$classeur, $p]),
            'preview_url' => $this->canPreviewFile($p->original_name, $p->mime_type)
                ? route('operations-diverses.justificatif.preview', [$classeur, $p])
                : null,
            'is_piece_comptable' => false,
        ]);

        $piecePdfName = 'piece-comptable-'.Str::slug((string) $classeur->numero_piece, '_').'.pdf';
        $items->push([
            'id' => 'piece-comptable',
            'description' => 'Pièce comptable',
            'original_name' => $piecePdfName,
            'url' => route('operations-diverses.piece-comptable.pdf', $classeur),
            'preview_url' => route('operations-diverses.piece-comptable.pdf', [$classeur, 'inline' => 1]),
            'is_piece_comptable' => true,
        ]);

        return $items->values();
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function apercuPayload(array $parsed): array
    {
        $rows = array_map(static function (array $row): array {
            return [
                'numero' => $row['numero'] ?? '',
                'code_agence' => $row['code_agence'] ?? '',
                'no_compte' => $row['no_compte'] ?? '',
                'sens' => $row['_sens'] ?? ($row['sens'] ?? ''),
                'montant' => $row['_montant'] ?? 0,
                'code_operation' => $row['code_operation'] ?? '',
                'libelle_ecriture' => $row['libelle_ecriture'] ?? '',
                'date_de_valeur' => $row['date_de_valeur'] ?? '',
            ];
        }, $parsed['rows']);

        return [
            'rows' => array_slice($rows, 0, 200),
            'total_rows' => count($rows),
            'nb_credit' => $parsed['nb_credit'],
            'nb_debit' => $parsed['nb_debit'],
            'total_credit' => $parsed['total_credit'],
            'total_debit' => $parsed['total_debit'],
            'difference' => round($parsed['total_debit'] - $parsed['total_credit'], 2),
            'devise' => $parsed['devise'],
            'error' => $parsed['error'],
        ];
    }

    /**
     * @return list<string|\Illuminate\Validation\Rules\File>
     */
    private function justificatifFileRules(bool $required = true): array
    {
        $file = \Illuminate\Validation\Rules\File::types([
            'pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'csv', 'txt',
            'xlsx', 'xls', 'doc', 'docx', 'eml', 'message/rfc822',
        ])->max(15360);

        return $required ? ['required', $file] : ['nullable', $file];
    }

    private function canPreviewFile(string $filename, ?string $mime = null): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'csv', 'txt', 'eml'], true)) {
            return true;
        }

        if ($mime === null) {
            return false;
        }

        return str_starts_with($mime, 'image/')
            || $mime === 'application/pdf'
            || $mime === 'message/rfc822'
            || str_starts_with($mime, 'text/');
    }

    /**
     * @return array<string, mixed>
     */
    private function integrationListPayload(OdClasseur $classeur): array
    {
        return [
            'id' => $classeur->id,
            'nom_classeur' => $classeur->nom_classeur,
            'numero_batch' => $classeur->numero_batch,
            'date_valeur' => optional($classeur->date_valeur)->toDateString(),
            'user_name' => $classeur->user?->name,
            'created_at' => optional($classeur->created_at)->toIso8601String(),
            'justificatifs_count' => $classeur->pieces->count() + 1,
            'resume_url' => route('operations-diverses.piece-comptable.resume', $classeur),
            'valider_url' => route('operations-diverses.piece-comptable.valider', $classeur),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function folderPayload(OdClasseur $classeur): array
    {
        return [
            'id' => $classeur->id,
            'nom_classeur' => $classeur->nom_classeur,
            'numero_piece' => $classeur->numero_piece,
            'numero_batch' => $classeur->numero_batch,
            'statut' => $classeur->statut,
            'user_id' => $classeur->user_id,
            'user_name' => $classeur->user?->name,
            'date_valeur' => optional($classeur->date_valeur)->toDateString(),
            'archive_date' => optional($classeur->archive_date)->toDateString(),
            'archived_at' => optional($classeur->archived_at)->toIso8601String(),
            'created_at' => optional($classeur->created_at)->toIso8601String(),
            'integrated_at' => optional($classeur->integrated_at)->toIso8601String(),
            'resume_url' => route('operations-diverses.piece-comptable.resume', $classeur),
            'pieces' => $this->piecesJustificativesPayload($classeur),
        ];
    }

    /**
     * @param  list<array{description: string, file?: \Illuminate\Http\UploadedFile}>  $justificatifs
     */
    private function persistClasseurBrouillon(
        Request $request,
        array $baseFields,
        callable $storeIntegrationFile,
        string $integrationOriginalName,
        array $justificatifs,
    ): OdClasseur {
        return DB::transaction(function () use ($request, $baseFields, $storeIntegrationFile, $integrationOriginalName, $justificatifs) {
            $classeur = OdClasseur::create([
                'user_id' => auth()->id(),
                'nom_classeur' => $baseFields['nom_classeur'],
                'date_valeur' => $baseFields['date_valeur'],
                'numero_batch' => $baseFields['numero_batch'],
                'numero_piece' => $baseFields['numero_batch'],
                'statut' => 'brouillon',
                'archive_date' => now()->toDateString(),
                'archived_at' => now(),
            ]);

            $baseDir = 'od/classeurs/'.$classeur->id;
            $integPath = $storeIntegrationFile($baseDir);

            $classeur->update([
                'fichier_integration_path' => $integPath,
                'fichier_integration_original_name' => $integrationOriginalName,
            ]);

            foreach ($justificatifs as $index => $row) {
                $file = $request->file('justificatifs.'.$index.'.file');
                $path = $file->store($baseDir.'/pieces', 'local');
                OdClasseurPiece::create([
                    'od_classeur_id' => $classeur->id,
                    'description' => $row['description'],
                    'original_name' => $file->getClientOriginalName(),
                    'storage_path' => $path,
                    'size' => $file->getSize(),
                    'mime_type' => $file->getClientMimeType(),
                    'sort_order' => $index,
                ]);
            }

            return $classeur->fresh();
        });
    }

    private function finalizeClasseurCreation(OdClasseur $classeur): RedirectResponse
    {
        try {
            $this->genererPieceComptable($classeur->fresh('user'));
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('operations-diverses.piece-comptable.resume', $classeur);
    }

    private function appendJustificatifsFromRequest(Request $request, OdClasseur $classeur): void
    {
        $rows = $request->input('justificatifs');
        if (! is_array($rows) || $rows === []) {
            return;
        }

        $indices = [];
        foreach ($rows as $index => $row) {
            if ($request->hasFile('justificatifs.'.$index.'.file')) {
                $indices[] = $index;
            }
        }

        if ($indices === []) {
            return;
        }

        $rules = [];
        foreach ($indices as $index) {
            $rules['justificatifs.'.$index.'.description'] = ['required', 'string', 'max:1000'];
            $rules['justificatifs.'.$index.'.file'] = $this->justificatifFileRules();
        }
        $request->validate($rules);

        $classeur->load('pieces');
        $sortOrder = (int) ($classeur->pieces->max('sort_order') ?? -1) + 1;
        $baseDir = 'od/classeurs/'.$classeur->id;

        foreach ($indices as $index) {
            $file = $request->file('justificatifs.'.$index.'.file');
            if (! $file) {
                continue;
            }

            $path = $file->store($baseDir.'/pieces', 'local');
            OdClasseurPiece::create([
                'od_classeur_id' => $classeur->id,
                'description' => (string) $request->input('justificatifs.'.$index.'.description'),
                'original_name' => $file->getClientOriginalName(),
                'storage_path' => $path,
                'size' => $file->getSize(),
                'mime_type' => $file->getClientMimeType(),
                'sort_order' => $sortOrder++,
            ]);
        }
    }

    private function finalizeClasseurUpdate(OdClasseur $classeur): RedirectResponse
    {
        try {
            $this->genererPieceComptable($classeur->fresh('user'));
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()
            ->route('operations-diverses.piece-comptable.resume', $classeur)
            ->with('success', 'Intégration modifiée.');
    }

    private function isManuelleIntegration(OdClasseur $classeur): bool
    {
        return Storage::disk('local')->exists('od/classeurs/'.$classeur->id.'/integration/manual-lines.json');
    }

    private function genererPieceComptable(OdClasseur $classeur): void
    {
        $parsed = $this->parseIntegration($classeur);

        $userId = '';
        if (! empty($parsed['rows'])) {
            $userId = (string) ($parsed['rows'][0]['user_id'] ?? '');
        }
        if ($userId === '') {
            $userId = $classeur->user?->flexComptaUserIdentifier() ?: ($classeur->user?->name ?? '');
        }

        $integratedAt = $classeur->integrated_at instanceof Carbon ? $classeur->integrated_at : now();

        $pdf = Pdf::loadView('operations-diverses.piece-comptable', [
            'classeur' => $classeur,
            'parsed' => $parsed,
            'userId' => $userId,
            'date' => $integratedAt->format('d/m/Y'),
            'heure' => $integratedAt->format('H:i:s'),
        ])->setPaper('a4', 'landscape');

        $path = 'od/classeurs/'.$classeur->id.'/piece/piece-'.Str::slug((string) $classeur->numero_piece, '_').'.pdf';
        Storage::disk('local')->put($path, $pdf->output());

        $classeur->forceFill(['piece_pdf_path' => $path])->save();
    }

    private function integrationContents(OdClasseur $classeur): ?string
    {
        if (! $classeur->fichier_integration_path) {
            return null;
        }
        if (! Storage::disk('local')->exists($classeur->fichier_integration_path)) {
            return null;
        }

        return Storage::disk('local')->get($classeur->fichier_integration_path);
    }

    private function refreshStoredIntegrationCsv(OdClasseur $classeur, string $numeroBatch, string $dateValeur): void
    {
        $contents = $this->integrationContents($classeur);
        if ($contents === null) {
            return;
        }

        $parsed = OdIntegrationCsv::parse($contents);
        if ($parsed['error'] !== null || $parsed['rows'] === []) {
            return;
        }

        $lignes = [];
        foreach ($parsed['rows'] as $row) {
            $lignes[] = [
                'date_de_valeur' => $dateValeur,
                'code_agence' => (string) ($row['code_agence'] ?? ''),
                'no_compte' => (string) ($row['no_compte'] ?? ''),
                'related_account' => '',
                'montant' => (string) ($row['_montant'] ?? $row['montant'] ?? ''),
                'sens' => (string) ($row['_sens'] ?? $row['sens'] ?? ''),
                'libelle_ecriture' => (string) ($row['libelle_ecriture'] ?? ''),
                'code_operation' => (string) ($row['code_operation'] ?? ''),
            ];
        }

        $user = $classeur->user ?? auth()->user();
        if ($user === null) {
            return;
        }

        $newContents = OdManualIntegrationCsv::build($numeroBatch, $lignes, $user);
        Storage::disk('local')->put($classeur->fichier_integration_path, $newContents);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseIntegration(OdClasseur $classeur): array
    {
        $parsed = OdIntegrationCsv::parse($this->integrationContents($classeur));
        $manualLines = $this->manualLinesMeta($classeur);
        if ($manualLines === []) {
            return $parsed;
        }

        foreach ($parsed['rows'] as $i => &$row) {
            if (isset($manualLines[$i]['related_account'])) {
                $row['related_account'] = $manualLines[$i]['related_account'];
            }
        }
        unset($row);

        return $parsed;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function manualLinesMeta(OdClasseur $classeur): array
    {
        $baseDir = 'od/classeurs/'.$classeur->id.'/integration/manual-lines.json';
        if (! Storage::disk('local')->exists($baseDir)) {
            return [];
        }

        $decoded = json_decode(Storage::disk('local')->get($baseDir) ?: '[]', true);

        return is_array($decoded) ? $decoded : [];
    }

    private function authorizeClasseur(OdClasseur $classeur): void
    {
        $user = auth()->user();
        if ($this->voitTousLesClasseurs($user)) {
            return;
        }
        abort_unless($classeur->user_id === $user->id, 403);
    }

    private function voitTousLesClasseurs(\App\Models\User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasRole('it') || $user->hasRole('admin');
    }
}
