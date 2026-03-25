<?php

namespace App\Http\Controllers;

use App\Models\EcritureComptable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class EcritureComptableController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);
        
        $ecritures = EcritureComptable::with('user')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return Inertia::render('EcrituresComptables/Index', [
            'ecritures' => $ecritures,
        ]);
    }

    public function export(Request $request)
    {
        $ecritures = EcritureComptable::with('user')->orderByDesc('created_at')->get();
        $filename = 'ecritures_comptables_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($ecritures) {
            echo "\xEF\xBB\xBF";
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'numero',
                'no_batch',
                'no_compte',
                'sens',
                'montant',
                'code_operation',
                'date_de_valeur',
                'code_agence',
                'libelle_ecriture',
                'user_id',
                'annee_comptable',
                'mois_comptable',
            ], ';');

            foreach ($ecritures as $ecriture) {
                fputcsv($handle, [
                    $ecriture->numero,
                    $ecriture->no_batch,
                    $ecriture->no_compte,
                    $ecriture->sens,
                    $ecriture->montant,
                    $ecriture->code_operation,
                    $ecriture->date_de_valeur ? $ecriture->date_de_valeur->format('Y-m-d') : '',
                    $ecriture->code_agence,
                    $ecriture->libelle_ecriture,
                    $ecriture->user_id,
                    $ecriture->annee_comptable,
                    $ecriture->mois_comptable,
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
