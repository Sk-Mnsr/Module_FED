<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Construit le fichier CSV Flex à partir de lignes saisies manuellement (OD).
 */
final class OdManualIntegrationCsv
{
    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public static function build(string $numeroBatch, array $lines, User $user): string
    {
        $c = config('services.ecritures_comptables_import', []);
        $delimiter = (string) ($c['csv_delimiter'] ?? ';');
        $dateFormat = (string) ($c['csv_date_format'] ?? 'd/m/Y');
        $decSep = (string) ($c['csv_decimal_separator'] ?? '.');
        $thouSep = (string) ($c['csv_thousands_separator'] ?? '');
        $montantDecimals = (int) ($c['csv_montant_decimals'] ?? 2);

        $header = EcritureComptableFlexCsv::headerRow();
        $flexUserId = $user->flexComptaUserIdentifier();

        $rows = [];
        foreach ($lines as $index => $line) {
            $date = self::parseDate((string) ($line['date_de_valeur'] ?? ''));
            $montant = (float) ($line['montant'] ?? 0);
            $montantStr = number_format($montant, max(0, min(10, $montantDecimals)), $decSep, $thouSep);

            $rows[] = [
                (string) ($index + 1),
                $numeroBatch,
                (string) ($line['no_compte'] ?? ''),
                strtoupper(substr((string) ($line['sens'] ?? ''), 0, 1)),
                $montantStr,
                (string) ($line['code_operation'] ?? ''),
                $date ? $date->format($dateFormat) : '',
                (string) ($line['code_agence'] ?? ''),
                (string) ($line['libelle_ecriture'] ?? ''),
                $flexUserId,
                $date ? 'FY('.$date->format('Y').')' : '',
                $date ? 'M'.$date->format('m') : '',
            ];
        }

        $buffer = fopen('php://temp', 'r+');
        if ($buffer === false) {
            return '';
        }

        if (EcritureComptableFlexCsv::includeBom()) {
            fwrite($buffer, "\xEF\xBB\xBF");
        }

        fputcsv($buffer, $header, $delimiter);
        foreach ($rows as $row) {
            fputcsv($buffer, $row, $delimiter);
        }

        rewind($buffer);
        $contents = stream_get_contents($buffer) ?: '';
        fclose($buffer);

        return $contents;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, string>
     */
    public static function validateLines(array $lines): array
    {
        $errors = [];

        if ($lines === []) {
            $errors['lignes'] = 'Ajoutez au moins une ligne OD.';

            return $errors;
        }

        foreach ($lines as $i => $line) {
            $n = $i + 1;
            if (trim((string) ($line['date_de_valeur'] ?? '')) === '') {
                $errors["lignes.{$i}.date_de_valeur"] = "Ligne {$n} : la date est obligatoire.";
            }
            if (trim((string) ($line['code_agence'] ?? '')) === '') {
                $errors["lignes.{$i}.code_agence"] = "Ligne {$n} : l'agence est obligatoire.";
            }
            if (trim((string) ($line['no_compte'] ?? '')) === '') {
                $errors["lignes.{$i}.no_compte"] = "Ligne {$n} : le N° compte est obligatoire.";
            }
            $montant = (float) ($line['montant'] ?? 0);
            if ($montant <= 0) {
                $errors["lignes.{$i}.montant"] = "Ligne {$n} : le montant doit être supérieur à 0.";
            }
            $sens = strtoupper(substr((string) ($line['sens'] ?? ''), 0, 1));
            if (! in_array($sens, ['C', 'D'], true)) {
                $errors["lignes.{$i}.sens"] = "Ligne {$n} : le sens doit être C ou D.";
            }
            if (trim((string) ($line['libelle_ecriture'] ?? '')) === '') {
                $errors["lignes.{$i}.libelle_ecriture"] = "Ligne {$n} : le libellé est obligatoire.";
            }
            if (trim((string) ($line['code_operation'] ?? '')) === '') {
                $errors["lignes.{$i}.code_operation"] = "Ligne {$n} : le code opération est obligatoire.";
            }
        }

        return $errors;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public static function classeurDateValeur(array $lines): ?string
    {
        foreach ($lines as $line) {
            $date = self::parseDate((string) ($line['date_de_valeur'] ?? ''));
            if ($date !== null) {
                return $date->toDateString();
            }
        }

        return null;
    }

    private static function parseDate(string $value): ?Carbon
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
            }
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
                return Carbon::createFromFormat('d/m/Y', $value)->startOfDay();
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }
}
