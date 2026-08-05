<?php

namespace App\Http\Controllers\Reconciliation;

use App\Http\Controllers\Controller;
use App\Models\Partenaire;
use App\Models\ReconciliationRun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HistoriqueController extends Controller
{
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
}
