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
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class HistoriqueController extends Controller
{
    public function __construct(
        protected ReconciliationGatewayClient $gateway
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'partenaire_id' => ['nullable', 'integer', 'exists:partenaires,id'],
            'mode' => ['nullable', 'string', 'in:two_pointers,agence'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $q = trim((string) ($validated['q'] ?? ''));
        $partenaireId = $validated['partenaire_id'] ?? null;
        $mode = $validated['mode'] ?? null;
        $perPage = (int) ($validated['per_page'] ?? 15);

        $runs = ReconciliationRun::query()
            ->with(['user:id,name,email', 'partenaire:id,identifiant,nom,icone'])
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(function ($inner) use ($like) {
                    $inner->where('partenaire_identifiant', 'ilike', $like)
                        ->orWhere('partenaire_nom', 'ilike', $like)
                        ->orWhere('excel_filename', 'ilike', $like);
                });
            })
            ->when($partenaireId, fn ($query) => $query->where('partenaire_id', $partenaireId))
            ->when($mode, fn ($query) => $query->where('mode', $mode))
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (ReconciliationRun $run) => [
                'id' => $run->id,
                'partenaire_id' => $run->partenaire_id,
                'partenaire_identifiant' => $run->partenaire_identifiant,
                'partenaire_nom' => $run->partenaire_nom,
                'partenaire_icone_url' => $run->partenaire?->icone_url,
                'date_debut' => $run->date_debut?->format('Y-m-d'),
                'date_fin' => $run->date_fin?->format('Y-m-d'),
                'mode' => $run->mode,
                'taux_reussite' => $run->taux_reussite,
                'reconcilies' => $run->reconcilies,
                'total' => $run->total,
                'excel_filename' => $run->excel_filename,
                'excel_url' => $run->excel_url,
                'status' => $run->status,
                'user_name' => $run->user?->name,
                'created_at' => $run->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                'can_relancer' => $run->canRelaunch(),
                'relancer_url' => route('reconciliation-flexcube.historique.relancer', $run),
                'ouvrir_url' => $this->ouvrirUrl($run),
            ]);

        $partenaires = Partenaire::query()
            ->orderBy('nom')
            ->get(['id', 'identifiant', 'nom'])
            ->map(fn (Partenaire $p) => [
                'id' => $p->id,
                'identifiant' => $p->identifiant,
                'nom' => $p->nom,
            ])
            ->values();

        return Inertia::render('ReconciliationFlexcube/Historique/Index', [
            'runs' => $runs,
            'partenaires' => $partenaires,
            'filters' => [
                'q' => $q,
                'partenaire_id' => $partenaireId ? (int) $partenaireId : null,
                'mode' => $mode ?? '',
            ],
        ]);
    }

    public function download(ReconciliationRun $run): StreamedResponse
    {
        abort_unless(filled($run->excel_path) && Storage::disk('public')->exists($run->excel_path), 404);

        $filename = $run->excel_filename ?: basename($run->excel_path);

        return Storage::disk('public')->download(
            $run->excel_path,
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    public function relancer(ReconciliationRun $run): JsonResponse
    {
        $partenaire = $run->partenaire;

        if ($partenaire === null) {
            return response()->json(['ok' => false, 'message' => 'Partenaire introuvable pour ce run.'], 404);
        }

        if (! $run->canRelaunch()) {
            return response()->json([
                'ok' => false,
                'message' => 'Fichier source non disponible pour un relancement automatique.',
                'ouvrir_url' => $this->ouvrirUrl($run),
            ], 422);
        }

        if (! $this->gateway->isConfigured()) {
            return response()->json(['ok' => false, 'message' => 'Gateway de réconciliation non configuré.'], 503);
        }

        $sources = ReconciliationSourceFiles::usableSources($run->source_files);
        $dateDebut = $run->date_debut?->format('Y-m-d');
        $dateFin = $run->date_fin?->format('Y-m-d');
        $mode = in_array($run->mode, ['two_pointers', 'agence'], true) ? $run->mode : 'two_pointers';

        try {
            $this->gateway->charger(
                $partenaire->identifiant,
                $sources,
                Carbon::parse((string) $dateDebut)->format('d/m/Y'),
                Carbon::parse((string) $dateFin)->format('d/m/Y'),
            );

            $this->gateway->run($partenaire->identifiant, $mode);

            $taux = [];
            try {
                $taux = $this->gateway->reconciliationTaux($partenaire->identifiant, $mode);
            } catch (Throwable) {
                // Résultat gateway OK même si le taux échoue.
            }

            return response()->json([
                'ok' => true,
                'message' => 'Réconciliation « '.$partenaire->nom.' » relancée avec succès (non ajoutée à l’historique).',
                'partenaire' => $partenaire->nom,
                'mode' => $mode,
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
                'taux_reussite' => isset($taux['taux_reussite']) ? (float) $taux['taux_reussite'] : null,
                'reconcilies' => isset($taux['reconcilies']) ? (int) $taux['reconcilies'] : null,
                'total' => isset($taux['total']) ? (int) $taux['total'] : null,
                'ouvrir_url' => $this->ouvrirUrl($run, relaunched: true),
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'Échec du relancement : '.$e->getMessage(),
            ], 502);
        }
    }

    private function ouvrirUrl(ReconciliationRun $run, bool $relaunched = false): string
    {
        $params = array_filter([
            'date_debut' => $run->date_debut?->format('Y-m-d'),
            'date_fin' => $run->date_fin?->format('Y-m-d'),
            'mode' => $run->mode,
            'from_run' => $run->id,
            'relaunched' => $relaunched ? 1 : null,
        ], static fn ($v) => $v !== null && $v !== '');

        $qs = http_build_query($params);

        return '/reconciliation-flexcube/reconciliation/'.$run->partenaire_id.($qs !== '' ? '?'.$qs : '');
    }
}
