<?php

namespace App\Http\Controllers;

use App\Models\OdClasseur;
use App\Models\OdClasseurPiece;
use App\Services\Integrations\EcritureComptableImportApiClient;
use App\Support\OdArchivage;
use App\Support\OdIntegrationCsv;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
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
            'justificatifs.*.file' => ['required', 'file', 'max:15360'],
        ]);

        $integFile = $request->file('fichier_integration');

        $classeur = DB::transaction(function () use ($request, $validated, $integFile) {
            $classeur = OdClasseur::create([
                'user_id' => auth()->id(),
                'nom_classeur' => $validated['nom_classeur'],
                'date_valeur' => $validated['date_valeur'],
                'numero_batch' => $validated['numero_batch'],
                'numero_piece' => $validated['numero_batch'],
                'statut' => 'brouillon',
                // Classement électronique : horodatage et date de dossier dès l'enregistrement.
                'archive_date' => now()->toDateString(),
                'archived_at' => now(),
            ]);

            $baseDir = 'od/classeurs/'.$classeur->id;

            $integPath = $integFile->store($baseDir.'/integration', 'local');
            $classeur->update([
                'fichier_integration_path' => $integPath,
                'fichier_integration_original_name' => $integFile->getClientOriginalName(),
            ]);

            foreach ($validated['justificatifs'] as $index => $row) {
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

        // Génère la pièce comptable dès l'enregistrement (indépendant de l'envoi plateforme).
        try {
            $this->genererPieceComptable($classeur->fresh('user'));
        } catch (\Throwable $e) {
            report($e);
        }

        // Affiche le résumé/aperçu avant validation et transmission à la plateforme.
        return redirect()->route('operations-diverses.piece-comptable.resume', $classeur);
    }

    public function pieceComptableResume(OdClasseur $classeur, EcritureComptableImportApiClient $importApi): InertiaResponse
    {
        $this->authorizeClasseur($classeur);
        $classeur->load(['user', 'pieces']);

        $parsed = OdIntegrationCsv::parse($this->integrationContents($classeur));

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

        return $redirect->with(
            'success',
            'Intégration validée et transmise à la plateforme. Pièce comptable générée et archivée.'
        );
    }

    public function pieceComptablePdf(OdClasseur $classeur): StreamedResponse
    {
        $this->authorizeClasseur($classeur);

        $classeur->load('user');

        if (! $classeur->piece_pdf_path || ! Storage::disk('local')->exists($classeur->piece_pdf_path)) {
            $this->genererPieceComptable($classeur);
            $classeur->refresh();
        }

        $filename = 'piece-comptable-'.Str::slug((string) $classeur->numero_piece, '_').'.pdf';

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

    public function archivage(Request $request, EcritureComptableImportApiClient $importApi): InertiaResponse
    {
        $user = auth()->user();
        $canViewAllAgents = $this->voitTousLesClasseurs($user);

        $filters = OdArchivage::normalizeFilters($request->only([
            'q',
            'nom_classeur',
            'numero_batch',
            'statut',
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
            ->with(['user', 'pieces']);

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
            'pdf_url' => route('operations-diverses.piece-comptable.pdf', $classeur),
            'pieces' => $classeur->pieces->map(fn (OdClasseurPiece $p) => [
                'id' => $p->id,
                'description' => $p->description,
                'original_name' => $p->original_name,
                'url' => route('operations-diverses.justificatif.download', [$classeur, $p]),
            ])->values(),
        ];
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
            'devise' => $parsed['devise'],
            'error' => $parsed['error'],
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
            'pdf_url' => route('operations-diverses.piece-comptable.pdf', $classeur),
            'resume_url' => route('operations-diverses.piece-comptable.resume', $classeur),
            'pieces' => $classeur->pieces->map(fn (OdClasseurPiece $p) => [
                'id' => $p->id,
                'description' => $p->description,
                'original_name' => $p->original_name,
                'url' => route('operations-diverses.justificatif.download', [$classeur, $p]),
            ])->values(),
        ];
    }

    private function genererPieceComptable(OdClasseur $classeur): void
    {
        $parsed = OdIntegrationCsv::parse($this->integrationContents($classeur));

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
