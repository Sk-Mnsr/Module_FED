<?php

namespace App\Support;

use App\Models\PodDataTable;
use App\Models\PodDataTableColumn;
use App\Models\PodDataTableRow;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Import CSV / Excel dans une table source Produits divers.
 * Première ligne = en-têtes (codes colonnes).
 */
final class PodDataTableImport
{
    /**
     * @return array{created: int, updated_columns: int, skipped: int, errors: list<string>}
     */
    public static function fromPath(PodDataTable $table, string $path, bool $replaceRows = true): array
    {
        $stats = ['created' => 0, 'updated_columns' => 0, 'skipped' => 0, 'errors' => []];

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $e) {
            $stats['errors'][] = 'Lecture fichier impossible : '.$e->getMessage();

            return $stats;
        }

        $sheet = $spreadsheet->getSheet(0);
        $rows = $sheet->toArray(null, true, false, false);

        if ($rows === []) {
            $stats['errors'][] = 'Fichier vide.';

            return $stats;
        }

        $headers = [];
        foreach ($rows[0] ?? [] as $i => $raw) {
            $code = self::normalizeHeader((string) ($raw ?? ''));
            if ($code === '') {
                continue;
            }
            $headers[$i] = $code;
        }

        if ($headers === []) {
            $stats['errors'][] = 'Aucun en-tête de colonne détecté.';

            return $stats;
        }

        DB::transaction(function () use ($table, $headers, $rows, $replaceRows, &$stats) {
            $existing = $table->columns()->pluck('id', 'code');
            $order = (int) $table->columns()->max('sort_order');

            foreach (array_values($headers) as $code) {
                if ($existing->has($code)) {
                    continue;
                }
                $order++;
                PodDataTableColumn::create([
                    'pod_data_table_id' => $table->id,
                    'code' => $code,
                    'libelle' => str_replace('_', ' ', $code),
                    'actif' => true,
                    'sort_order' => $order,
                ]);
                $stats['updated_columns']++;
            }

            if ($replaceRows) {
                $table->rows()->delete();
            }

            $sort = $replaceRows ? 0 : ((int) $table->rows()->max('sort_order') + 1);

            foreach (array_slice($rows, 1) as $cells) {
                $data = [];
                $hasValue = false;
                foreach ($headers as $i => $code) {
                    $value = trim((string) ($cells[$i] ?? ''));
                    $data[$code] = $value;
                    if ($value !== '') {
                        $hasValue = true;
                    }
                }

                if (! $hasValue) {
                    $stats['skipped']++;
                    continue;
                }

                PodDataTableRow::create([
                    'pod_data_table_id' => $table->id,
                    'data' => $data,
                    'actif' => true,
                    'sort_order' => $sort++,
                ]);
                $stats['created']++;
            }
        });

        return $stats;
    }

    private static function normalizeHeader(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/', '_', $value) ?? $value;
        $value = preg_replace('/[^A-Za-z0-9_]/', '', $value) ?? $value;

        return strtoupper($value);
    }
}
