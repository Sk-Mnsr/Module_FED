<?php

namespace App\Support;

use App\Models\User;

/**
 * CSV simplifié pour l'intégration automatique OD.
 * L'utilisateur ne saisit que les écritures ; batch, date, user_id et période comptable
 * sont complétés depuis le formulaire et l’utilisateur connecté.
 */
final class OdSimpleIntegrationCsv
{
    /**
     * @return list<string>
     */
    public static function headerRow(): array
    {
        return [
            'no_compte',
            'sens',
            'montant',
            'code_operation',
            'code_agence',
            'libelle_ecriture',
        ];
    }

    public static function buildTemplate(): string
    {
        $c = config('services.ecritures_comptables_import', []);
        $delimiter = (string) ($c['csv_delimiter'] ?? ';');

        $rows = [
            ['4211000001', 'D', '1000', 'OD01', '500', 'Exemple écriture débit'],
            ['5211000001', 'C', '1000', 'OD01', '500', 'Exemple écriture crédit'],
        ];

        return self::writeCsv(self::headerRow(), $rows, $delimiter);
    }

    /**
     * @param  list<string>  $headerLower
     */
    public static function isSimpleFormat(array $headerLower): bool
    {
        if (in_array('no_batch', $headerLower, true)
            || in_array('user_id', $headerLower, true)
            || in_array('annee_comptable', $headerLower, true)) {
            return false;
        }

        $required = array_map('strtolower', self::headerRow());

        foreach ($required as $col) {
            if (! in_array($col, $headerLower, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{header: list<string>, rows: list<array<string, string>>, error: string|null}
     */
    public static function parse(?string $contents): array
    {
        $empty = ['header' => [], 'rows' => [], 'error' => null];

        if ($contents === null || trim($contents) === '') {
            $empty['error'] = 'Le fichier CSV est vide.';

            return $empty;
        }

        $c = config('services.ecritures_comptables_import', []);
        $delimiter = OdIntegrationCsv::detectDelimiterPublic(
            preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents,
            (string) ($c['csv_delimiter'] ?? ';')
        );

        if ($delimiter !== (string) ($c['csv_delimiter'] ?? ';')) {
            $empty['error'] = 'Format incorrect : utilisez le modèle avec le séparateur point-virgule (;).';

            return $empty;
        }

        $lines = self::readLines($contents, $delimiter);
        if ($lines === []) {
            $empty['error'] = 'Aucune ligne exploitable dans le fichier CSV.';

            return $empty;
        }

        $headerLower = array_map('strtolower', $lines[0]);
        if (! self::isSimpleFormat($headerLower)) {
            $empty['error'] = 'En-tête CSV simplifié invalide. Téléchargez le modèle fourni.';

            return $empty;
        }

        $header = $lines[0];
        $dataLines = array_slice($lines, 1);
        $rows = [];

        foreach ($dataLines as $cells) {
            $row = [];
            foreach ($header as $i => $key) {
                $row[strtolower($key)] = $cells[$i] ?? '';
            }
            $rows[] = $row;
        }

        return ['header' => $header, 'rows' => $rows, 'error' => null];
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    public static function validateRows(array $rows): ?string
    {
        if ($rows === []) {
            return 'Le fichier ne contient aucune écriture.';
        }

        $nbCredit = 0;
        $nbDebit = 0;

        foreach ($rows as $i => $row) {
            $n = $i + 1;
            if (trim((string) ($row['no_compte'] ?? '')) === '') {
                return "Ligne {$n} : le N° compte est obligatoire.";
            }
            $sens = strtoupper(substr((string) ($row['sens'] ?? ''), 0, 1));
            if (! in_array($sens, ['C', 'D'], true)) {
                return "Ligne {$n} : le sens doit être C ou D.";
            }
            $montant = (float) str_replace(',', '.', (string) ($row['montant'] ?? '0'));
            if ($montant <= 0) {
                return "Ligne {$n} : le montant doit être supérieur à 0.";
            }
            if (trim((string) ($row['code_operation'] ?? '')) === '') {
                return "Ligne {$n} : le code opération est obligatoire.";
            }
            if (trim((string) ($row['code_agence'] ?? '')) === '') {
                return "Ligne {$n} : l'agence est obligatoire.";
            }
            if (trim((string) ($row['libelle_ecriture'] ?? '')) === '') {
                return "Ligne {$n} : le libellé est obligatoire.";
            }

            if ($sens === 'C') {
                $nbCredit++;
            } else {
                $nbDebit++;
            }
        }

        if ($nbCredit === 0 && $nbDebit === 0) {
            return 'Aucune ligne avec sens C ou D détectée.';
        }

        return null;
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    public static function enrichToFlex(string $numeroBatch, string $dateValeur, array $rows, User $user): string
    {
        $lignes = [];
        foreach ($rows as $row) {
            $lignes[] = [
                'date_de_valeur' => $dateValeur,
                'code_agence' => (string) ($row['code_agence'] ?? ''),
                'no_compte' => (string) ($row['no_compte'] ?? ''),
                'related_account' => '',
                'montant' => (string) ($row['montant'] ?? ''),
                'sens' => strtoupper(substr((string) ($row['sens'] ?? ''), 0, 1)),
                'libelle_ecriture' => (string) ($row['libelle_ecriture'] ?? ''),
                'code_operation' => (string) ($row['code_operation'] ?? ''),
            ];
        }

        return OdManualIntegrationCsv::build($numeroBatch, $lignes, $user);
    }

    /**
     * @return array{contents: string|null, error: string|null}
     */
    public static function normalizeUpload(
        ?string $contents,
        string $numeroBatch,
        string $dateValeur,
        User $user,
    ): array {
        if ($contents === null || trim($contents) === '') {
            return ['contents' => null, 'error' => 'Le fichier CSV est vide.'];
        }

        $parsedSimple = self::parse($contents);
        if ($parsedSimple['error'] === null) {
            $rowError = self::validateRows($parsedSimple['rows']);
            if ($rowError !== null) {
                return ['contents' => null, 'error' => $rowError];
            }

            return [
                'contents' => self::enrichToFlex($numeroBatch, $dateValeur, $parsedSimple['rows'], $user),
                'error' => null,
            ];
        }

        $flexError = OdIntegrationCsv::validateForImport($contents);
        if ($flexError !== null) {
            return ['contents' => null, 'error' => $flexError];
        }

        return ['contents' => $contents, 'error' => null];
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private static function writeCsv(array $header, array $rows, string $delimiter): string
    {
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
     * @return list<list<string>>
     */
    private static function readLines(string $contents, string $delimiter): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return [];
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

        return $lines;
    }
}
