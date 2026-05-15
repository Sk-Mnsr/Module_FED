<?php

namespace App\Http\Controllers;

use App\Models\EcritureComptable;
use App\Services\Integrations\EcritureComptableImportApiClient;
use App\Support\EcritureComptableFlexCsv;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class EcritureComptableController extends Controller
{
    public function index(
        Request $request,
        EcritureComptableImportApiClient $importApi,
    ) {
        $perPage = (int) $request->get('per_page', 10);

        $ecritures = EcritureComptable::with(['user.profil'])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $user = $request->user();

        return Inertia::render('EcrituresComptables/Index', [
            'ecritures' => $ecritures,
            'comptableImportApiConfigured' => $importApi->isConfigured(),
            'canPushComptableImport' => $user && ($user->isSuperAdmin() || $user->hasRole('it')),
        ]);
    }

    /**
     * Envoie vers l’API : un fichier CSV (transfert direct) ou les enregistrements en base (ids optionnels = tout).
     */
    public function push(
        Request $request,
        EcritureComptableImportApiClient $importApi,
    ) {
        if (! $importApi->isConfigured()) {
            return back()->with('error', 'L’intégration comptable n’est pas configurée (URL / clé API).');
        }

        if ($request->hasFile('forward_file')) {
            $request->validate([
                'forward_file' => ['required', 'file', 'mimes:csv,txt', 'max:15360'],
            ]);
            $file = $request->file('forward_file');
            $raw = $file->getContent();
            $name = $file->getClientOriginalName() ?: (string) config('services.ecritures_comptables_import.csv_filename', 'RQFT.csv');

            try {
                $response = $importApi->uploadFileContents($raw, $name);
            } catch (\Throwable $e) {
                report($e);

                return back()->with('error', 'Envoi impossible : '.$e->getMessage());
            }

            if ($response->successful()) {
                $preview = Str::limit($response->body(), 500);

                return back()->with('success', 'Fichier transmis (HTTP '.$response->status().') : '.$preview);
            }

            return back()->with('error', 'Rejet (HTTP '.$response->status().') : '.Str::limit($response->body(), 1000));
        }

        $ids = $request->input('ids');
        $query = EcritureComptable::query()->with(['user.profil'])->orderBy('id');

        if (is_array($ids) && count($ids) > 0) {
            $query->whereIn('id', array_map('intval', $ids));
        }

        $ecritures = $query->get();

        if ($ecritures->isEmpty()) {
            return back()->with('error', 'Aucune écriture à envoyer.');
        }

        try {
            $response = $importApi->upload($ecritures);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Envoi impossible : '.$e->getMessage());
        }

        if ($response->successful()) {
            $preview = Str::limit($response->body(), 500);

            return back()->with('success', 'Réponse plateforme (HTTP '.$response->status().') : '.$preview);
        }

        return back()->with('error', 'Rejet (HTTP '.$response->status().') : '.Str::limit($response->body(), 1000));
    }

    public function export(Request $request)
    {
        $ecritures = EcritureComptable::with(['user.profil'])->orderByDesc('created_at')->get();
        $filename = 'ecritures_comptables_'.now()->format('Ymd_His').'.csv';

        $delimiter = (string) config('services.ecritures_comptables_import.csv_delimiter', ';');

        return response()->streamDownload(function () use ($ecritures, $delimiter) {
            if (EcritureComptableFlexCsv::includeBom()) {
                echo "\xEF\xBB\xBF";
            }
            $handle = fopen('php://output', 'w');

            fputcsv($handle, EcritureComptableFlexCsv::headerRow(), $delimiter);

            foreach ($ecritures as $ecriture) {
                fputcsv($handle, EcritureComptableFlexCsv::dataRow($ecriture), $delimiter);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
