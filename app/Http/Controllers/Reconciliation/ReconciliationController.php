<?php

namespace App\Http\Controllers\Reconciliation;

use App\Http\Controllers\Controller;
use App\Models\Partenaire;
use App\Models\ReconciliationRun;
use App\Services\Integrations\ReconciliationGatewayClient;
use App\Support\ReconciliationSourceFiles;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ReconciliationController extends Controller
{
    public function __construct(
        protected ReconciliationGatewayClient $gateway
    ) {}

    public function index(): InertiaResponse
    {
        $partenaires = Partenaire::query()
            ->actifs()
            ->orderBy('nom')
            ->get()
            ->map(fn (Partenaire $p) => [
                'id' => $p->id,
                'identifiant' => $p->identifiant,
                'nom' => $p->nom,
                'icone_url' => $p->icone_url,
            ])
            ->values();

        return Inertia::render('ReconciliationFlexcube/Reconciliation/Index', [
            'partenaires' => $partenaires,
        ]);
    }

    public function show(Request $request, Partenaire $partenaire): InertiaResponse
    {
        abort_unless($partenaire->actif, 404);

        $gatewayMode = null;
        $gatewayOnline = false;
        $gatewayError = null;

        if ($this->gateway->isConfigured()) {
            try {
                $this->gateway->health();
                $gatewayOnline = true;
                $gatewayMode = $this->gateway->resolveMode($partenaire->identifiant);
            } catch (Throwable $e) {
                $gatewayError = $e->getMessage();
            }
        } else {
            $gatewayError = 'RECONCILIATION_GATEWAY_URL non configurée.';
        }

        $prefill = null;
        $dateDebut = $request->query('date_debut');
        $dateFin = $request->query('date_fin');
        $mode = $request->query('mode');
        $fromRun = $request->query('from_run');
        $relaunched = $request->boolean('relaunched');

        if (filled($dateDebut) || filled($dateFin) || filled($mode) || filled($fromRun) || $relaunched) {
            $prefill = [
                'date_debut' => is_string($dateDebut) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDebut)
                    ? $dateDebut
                    : null,
                'date_fin' => is_string($dateFin) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFin)
                    ? $dateFin
                    : null,
                'mode' => is_string($mode) && in_array($mode, ['two_pointers', 'agence'], true)
                    ? $mode
                    : null,
                'from_run_id' => is_numeric($fromRun) ? (int) $fromRun : null,
                'relaunched' => $relaunched,
            ];

            if ($prefill['mode'] !== null) {
                $gatewayMode = $prefill['mode'];
            }
        }

        return Inertia::render('ReconciliationFlexcube/Reconciliation/Show', [
            'partenaire' => [
                'id' => $partenaire->id,
                'identifiant' => $partenaire->identifiant,
                'nom' => $partenaire->nom,
                'icone_url' => $partenaire->icone_url,
            ],
            'gateway' => [
                'online' => $gatewayOnline,
                'mode' => $gatewayMode,
                'error' => $gatewayError,
                'url' => $this->gateway->isConfigured() ? $this->gateway->baseUrl() : null,
            ],
            'prefill' => $prefill,
        ]);
    }

    public function charger(Request $request, Partenaire $partenaire): JsonResponse
    {
        $this->ensurePartenaireActif($partenaire);

        $validated = $request->validate([
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:51200'],
        ], [
            'date_debut.required' => 'La date début est obligatoire.',
            'date_fin.required' => 'La date fin est obligatoire.',
            'date_fin.after_or_equal' => 'La date fin doit être postérieure ou égale à la date début.',
            'files.required' => 'Ajoutez au moins un fichier partenaire.',
            'files.min' => 'Ajoutez au moins un fichier partenaire.',
        ]);

        try {
            $uploaded = array_values(array_filter(
                $request->file('files', []),
                static fn ($f) => $f instanceof \Illuminate\Http\UploadedFile
            ));

            $result = $this->gateway->charger(
                $partenaire->identifiant,
                $uploaded,
                $this->toGatewayDate($validated['date_debut']),
                $this->toGatewayDate($validated['date_fin']),
            );

            if ($request->user()) {
                ReconciliationSourceFiles::storePending(
                    (int) $request->user()->id,
                    (int) $partenaire->id,
                    $uploaded,
                );
            }

            return response()->json([
                'ok' => true,
                'data' => $result,
                'mode' => $result['mode'] ?? $this->gateway->resolveMode($partenaire->identifiant),
            ]);
        } catch (Throwable $e) {
            return $this->gatewayErrorResponse($e);
        }
    }

    public function run(Request $request, Partenaire $partenaire): StreamedResponse|JsonResponse|Response
    {
        $this->ensurePartenaireActif($partenaire);

        $validated = $request->validate([
            'mode' => ['nullable', 'string', 'in:two_pointers,agence'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
        ]);

        $mode = $validated['mode'] ?? null;
        if (! is_string($mode) || ! in_array($mode, ['two_pointers', 'agence'], true)) {
            try {
                $mode = $this->gateway->resolveMode($partenaire->identifiant);
            } catch (Throwable $e) {
                return $this->gatewayErrorResponse($e);
            }
        }

        try {
            $response = $this->gateway->run($partenaire->identifiant, $mode);
            $filename = sprintf(
                'reconciliation_%s_%s.xlsx',
                strtolower($partenaire->identifiant),
                now()->format('Ymd_His')
            );

            $body = $response->body();
            $contentType = $response->header('Content-Type')
                ?: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

            $excelPath = 'reconciliation/runs/'.$filename;
            Storage::disk('public')->put($excelPath, $body);

            $taux = [];
            $summary = [];
            try {
                $taux = $this->gateway->reconciliationTaux($partenaire->identifiant, $mode);
                $summary = $this->gateway->reconciliationSummary($partenaire->identifiant, $mode);
            } catch (Throwable) {
                // Historique créé même si le résumé gateway échoue.
            }

            $run = ReconciliationRun::create([
                'partenaire_id' => $partenaire->id,
                'partenaire_identifiant' => $partenaire->identifiant,
                'partenaire_nom' => $partenaire->nom,
                'date_debut' => $validated['date_debut'] ?? null,
                'date_fin' => $validated['date_fin'] ?? null,
                'mode' => $mode,
                'taux_reussite' => isset($taux['taux_reussite']) ? (float) $taux['taux_reussite'] : null,
                'reconcilies' => isset($taux['reconcilies']) ? (int) $taux['reconcilies'] : null,
                'total' => isset($taux['total']) ? (int) $taux['total'] : null,
                'taux_json' => $taux !== [] ? $taux : null,
                'summary_json' => $summary !== [] ? $summary : null,
                'excel_path' => $excelPath,
                'excel_filename' => $filename,
                'user_id' => $request->user()?->id,
                'status' => 'success',
            ]);

            if ($request->user()) {
                $pending = ReconciliationSourceFiles::takePending(
                    (int) $request->user()->id,
                    (int) $partenaire->id,
                );
                ReconciliationSourceFiles::attachToRun($run, $pending);
            }

            return response($body, 200, [
                'Content-Type' => $contentType,
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'X-Reconciliation-Mode' => $mode,
                'X-Reconciliation-Run-Id' => (string) $run->id,
            ]);
        } catch (Throwable $e) {
            try {
                ReconciliationRun::create([
                    'partenaire_id' => $partenaire->id,
                    'partenaire_identifiant' => $partenaire->identifiant,
                    'partenaire_nom' => $partenaire->nom,
                    'date_debut' => $validated['date_debut'] ?? null,
                    'date_fin' => $validated['date_fin'] ?? null,
                    'mode' => is_string($mode) ? $mode : 'two_pointers',
                    'user_id' => $request->user()?->id,
                    'status' => 'failed',
                    'error_message' => mb_substr($e->getMessage(), 0, 2000),
                ]);
            } catch (Throwable) {
                // Ne pas masquer l’erreur gateway.
            }

            return $this->gatewayErrorResponse($e);
        }
    }

    public function reset(Partenaire $partenaire): JsonResponse
    {
        $this->ensurePartenaireActif($partenaire);

        try {
            $result = $this->gateway->reset($partenaire->identifiant);

            return response()->json(['ok' => true, 'data' => $result]);
        } catch (Throwable $e) {
            return $this->gatewayErrorResponse($e);
        }
    }

    public function results(Request $request, Partenaire $partenaire): JsonResponse
    {
        $mode = $request->query('mode');
        if (! is_string($mode) || ! in_array($mode, ['two_pointers', 'agence'], true)) {
            try {
                $mode = $this->gateway->resolveMode($partenaire->identifiant);
            } catch (Throwable $e) {
                return $this->gatewayErrorResponse($e);
            }
        }

        try {
            $payload = [
                'ok' => true,
                'mode' => $mode,
                'summary' => $this->gateway->reconciliationSummary($partenaire->identifiant, $mode),
                'taux' => $this->gateway->reconciliationTaux($partenaire->identifiant, $mode),
                'resume' => $this->gateway->reconciliationResume($partenaire->identifiant, $mode),
            ];

            if ($mode === 'two_pointers') {
                try {
                    $payload['carte'] = $this->gateway->carteResume($partenaire->identifiant);
                } catch (Throwable) {
                    $payload['carte'] = null;
                }
            }

            return response()->json($payload);
        } catch (Throwable $e) {
            return $this->gatewayErrorResponse($e);
        }
    }

    public function health(): JsonResponse
    {
        if (! $this->gateway->isConfigured()) {
            return response()->json([
                'ok' => false,
                'message' => 'RECONCILIATION_GATEWAY_URL non configurée.',
            ], 503);
        }

        try {
            return response()->json([
                'ok' => true,
                'data' => $this->gateway->health(),
                'url' => $this->gateway->baseUrl(),
            ]);
        } catch (Throwable $e) {
            return $this->gatewayErrorResponse($e, 503);
        }
    }

    public function gatewayPartenaires(): JsonResponse
    {
        try {
            return response()->json(['ok' => true, 'data' => $this->gateway->partenaires()]);
        } catch (Throwable $e) {
            return $this->gatewayErrorResponse($e);
        }
    }

    public function gatewayStatuts(): JsonResponse
    {
        try {
            return response()->json(['ok' => true, 'data' => $this->gateway->statuts()]);
        } catch (Throwable $e) {
            return $this->gatewayErrorResponse($e);
        }
    }

    public function gatewayTables(): JsonResponse
    {
        try {
            return response()->json(['ok' => true, 'data' => $this->gateway->tables()]);
        } catch (Throwable $e) {
            return $this->gatewayErrorResponse($e);
        }
    }

    public function db(Request $request, Partenaire $partenaire, string $resource): JsonResponse
    {
        $limit = (int) $request->query('limit', 100);
        $limit = max(1, min($limit, 1000));

        try {
            $data = $this->gateway->db($resource, $partenaire->identifiant);
            $total = is_array($data) ? count($data) : null;
            $rows = is_array($data) && array_is_list($data)
                ? array_slice($data, 0, $limit)
                : $data;

            return response()->json([
                'ok' => true,
                'resource' => $resource,
                'total' => $total,
                'limit' => $limit,
                'data' => $rows,
            ]);
        } catch (Throwable $e) {
            return $this->gatewayErrorResponse($e);
        }
    }

    public function carte(Partenaire $partenaire): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'data' => $this->gateway->carteResume($partenaire->identifiant),
            ]);
        } catch (Throwable $e) {
            return $this->gatewayErrorResponse($e);
        }
    }

    public function grapheStatut(Partenaire $partenaire): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'data' => $this->gateway->grapheStatut($partenaire->identifiant),
            ]);
        } catch (Throwable $e) {
            return $this->gatewayErrorResponse($e);
        }
    }

    public function grapheEvolution(Request $request, Partenaire $partenaire): JsonResponse
    {
        $type = $request->query('type_transaction', 'W2B');
        if (! is_string($type) || ! in_array($type, ['W2B', 'B2W'], true)) {
            $type = 'W2B';
        }

        try {
            return response()->json([
                'ok' => true,
                'type_transaction' => $type,
                'data' => $this->gateway->grapheEvolution($partenaire->identifiant, $type),
            ]);
        } catch (Throwable $e) {
            return $this->gatewayErrorResponse($e);
        }
    }

    protected function ensurePartenaireActif(Partenaire $partenaire): void
    {
        abort_unless($partenaire->actif, 404);
    }

    protected function toGatewayDate(string $isoDate): string
    {
        return Carbon::parse($isoDate)->format('d/m/Y');
    }

    protected function gatewayErrorResponse(Throwable $e, int $status = 502): JsonResponse
    {
        $message = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Erreur inattendue lors de l’appel au gateway : '.$e->getMessage();

        return response()->json([
            'ok' => false,
            'message' => $message,
        ], $status);
    }
}
