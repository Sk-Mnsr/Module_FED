<?php

namespace App\Http\Controllers;

use App\Models\EcritureComptable;
use App\Support\EcritureComptableFlexCsv;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EcritureComptableController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);

        $ecritures = EcritureComptable::with(['user'])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return Inertia::render('EcrituresComptables/Index', [
            'ecritures' => $ecritures,
        ]);
    }

    public function export(Request $request)
    {
        $ecritures = EcritureComptable::with(['user'])->orderByDesc('created_at')->get();
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
