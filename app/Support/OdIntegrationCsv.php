<?php

namespace App\Support;

/**
 * Lit le fichier CSV d'intégration OD (format Flex : voir EcritureComptableFlexCsv)
 * pour produire un aperçu des écritures et les totaux Débit / Crédit utilisés
 * dans la pièce comptable générée.
 */
final class OdIntegrationCsv
{
    /**
     * @return array{
     *     header: list<string>,
     *     rows: list<array<string, string>>,
     *     nb_credit: int,
     *     nb_debit: int,
     *     total_credit: float,
     *     total_debit: float,
     *     devise: string,
     *     error: string|null
     * }
     */
    public static function parse(?string $contents): array
    {
        $empty = [
            'header' => [],
            'rows' => [],
            'nb_credit' => 0,
            'nb_debit' => 0,
            'total_credit' => 0.0,
            'total_debit' => 0.0,
            'devise' => self::devise(),
            'error' => null,
        ];

        if ($contents === null || trim($contents) === '') {
            $empty['error'] = 'Fichier d’intégration introuvable ou vide.';

            return $empty;
        }

        $c = config('services.ecritures_comptables_import', []);

        // Retire un éventuel BOM UTF-8.
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;

        // Détection automatique du séparateur (le fichier peut utiliser « , » ou « ; »).
        $delimiter = self::detectDelimiter($contents, (string) ($c['csv_delimiter'] ?? ';'));

        // Quand le séparateur est la virgule, elle ne peut pas servir de séparateur décimal.
        if ($delimiter === ',') {
            $decSep = '.';
            $thouSep = '';
        } else {
            $decSep = (string) ($c['csv_decimal_separator'] ?? '.');
            $thouSep = (string) ($c['csv_thousands_separator'] ?? '');
        }

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            $empty['error'] = 'Lecture du fichier CSV impossible.';

            return $empty;
        }
        fwrite($handle, $contents);
        rewind($handle);

        $lines = [];
        while (($cells = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($cells === [null] || (count($cells) === 1 && trim((string) ($cells[0] ?? '')) === '')) {
                continue;
            }
            $lines[] = array_map(static fn ($v) => trim((string) ($v ?? '')), $cells);
        }
        fclose($handle);

        if ($lines === []) {
            $empty['error'] = 'Aucune ligne exploitable dans le fichier CSV.';

            return $empty;
        }

        $expected = EcritureComptableFlexCsv::headerRow();

        // Détecte la présence d'une ligne d'en-tête.
        $first = array_map('strtolower', $lines[0]);
        $hasHeader = in_array('no_compte', $first, true) || in_array('numero', $first, true) || in_array('sens', $first, true);

        $header = $hasHeader ? $lines[0] : $expected;
        $dataLines = $hasHeader ? array_slice($lines, 1) : $lines;

        $rows = [];
        $nbCredit = 0;
        $nbDebit = 0;
        $totalCredit = 0.0;
        $totalDebit = 0.0;

        foreach ($dataLines as $cells) {
            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = $cells[$i] ?? '';
            }

            $sens = strtoupper(trim((string) ($row['sens'] ?? '')));
            $montant = self::toFloat((string) ($row['montant'] ?? ''), $decSep, $thouSep);

            $isCredit = str_starts_with($sens, 'C');
            $isDebit = str_starts_with($sens, 'D');

            if ($isCredit) {
                $nbCredit++;
                $totalCredit += $montant;
            } elseif ($isDebit) {
                $nbDebit++;
                $totalDebit += $montant;
            }

            $row['_montant'] = $montant;
            $row['_sens'] = $isCredit ? 'C' : ($isDebit ? 'D' : $sens);
            $rows[] = $row;
        }

        return [
            'header' => $header,
            'rows' => $rows,
            'nb_credit' => $nbCredit,
            'nb_debit' => $nbDebit,
            'total_credit' => round($totalCredit, 2),
            'total_debit' => round($totalDebit, 2),
            'devise' => self::devise(),
            'error' => null,
        ];
    }

    private static function detectDelimiter(string $contents, string $fallback): string
    {
        // Première ligne non vide.
        $firstLine = '';
        foreach (preg_split('/\r\n|\r|\n/', $contents) ?: [] as $line) {
            if (trim($line) !== '') {
                $firstLine = $line;
                break;
            }
        }

        if ($firstLine === '') {
            return $fallback;
        }

        $candidates = [';', ',', "\t", '|'];
        $best = $fallback;
        $bestCount = 0;
        foreach ($candidates as $candidate) {
            $count = substr_count($firstLine, $candidate);
            if ($count > $bestCount) {
                $bestCount = $count;
                $best = $candidate;
            }
        }

        return $bestCount > 0 ? $best : $fallback;
    }

    private static function devise(): string
    {
        return (string) config('services.ecritures_comptables_import.devise', 'XOF');
    }

    private static function toFloat(string $value, string $decSep, string $thouSep): float
    {
        $value = trim($value);
        if ($value === '') {
            return 0.0;
        }
        if ($thouSep !== '') {
            $value = str_replace($thouSep, '', $value);
        }
        if ($decSep !== '' && $decSep !== '.') {
            $value = str_replace($decSep, '.', $value);
        }
        // Conserve uniquement chiffres, signe et point décimal.
        $value = preg_replace('/[^0-9.\-]/', '', $value) ?? '0';

        return (float) $value;
    }
}
