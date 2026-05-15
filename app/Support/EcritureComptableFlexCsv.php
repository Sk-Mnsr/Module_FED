<?php

namespace App\Support;

use App\Models\EcritureComptable;

/**
 * Lignes CSV pour Flex Import : séparateur ; (config), dates jj/mm/aaaa, montants avec point décimal,
 * année FY(AAAA), mois MXX, user_id = identifiant Flexcube (voir écran « Indications du fichier »).
 */
final class EcritureComptableFlexCsv
{
    /**
     * @return list<string>
     */
    public static function headerRow(): array
    {
        return [
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
        ];
    }

    /**
     * @return list<string>
     */
    public static function dataRow(EcritureComptable $ecriture): array
    {
        $c = config('services.ecritures_comptables_import', []);
        $dateFormat = (string) ($c['csv_date_format'] ?? 'd/m/Y');
        $decSep = (string) ($c['csv_decimal_separator'] ?? '.');
        $thouSep = (string) ($c['csv_thousands_separator'] ?? '');
        $montantDecimals = (int) ($c['csv_montant_decimals'] ?? 2);

        $montant = $ecriture->montant;
        $montantStr = '';
        if ($montant !== null && $montant !== '') {
            $montantStr = number_format((float) $montant, max(0, min(10, $montantDecimals)), $decSep, $thouSep);
        }

        $dateStr = '';
        if ($ecriture->date_de_valeur) {
            $dateStr = $ecriture->date_de_valeur->format($dateFormat);
        }

        return [
            self::csvCell($ecriture->numero),
            self::csvCell($ecriture->no_batch),
            self::csvCell($ecriture->no_compte),
            self::csvCell($ecriture->sens),
            $montantStr,
            self::csvCell($ecriture->code_operation),
            $dateStr,
            self::csvCell($ecriture->code_agence),
            self::csvCell($ecriture->libelle_ecriture),
            $ecriture->user ? $ecriture->user->flexComptaUserIdentifier() : '',
            self::formatAnneeComptableFlex($ecriture->annee_comptable),
            self::formatMoisComptable($ecriture->mois_comptable),
        ];
    }

    public static function includeBom(): bool
    {
        $c = config('services.ecritures_comptables_import', []);

        return filter_var($c['csv_include_bom'] ?? true, FILTER_VALIDATE_BOOL);
    }

    /**
     * Flex : FY(AAAA), ex. FY(2024). Accepte FY2024, 2024, FY(2024) déjà correct.
     */
    private static function formatAnneeComptableFlex(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $s = trim((string) $value);
        if (preg_match('/^FY\s*\(\s*(\d{4})\s*\)\s*$/i', $s, $m)) {
            return 'FY('.(int) $m[1].')';
        }
        if (preg_match('/^FY\s*(\d{4})\s*$/i', $s, $m)) {
            return 'FY('.(int) $m[1].')';
        }
        if (preg_match('/^(\d{4})$/', $s, $m)) {
            return 'FY('.(int) $m[1].')';
        }

        return self::csvCell($value);
    }

    /**
     * Flex : MXX (ex. M06). Normalise M6 → M06.
     */
    private static function formatMoisComptable(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $s = strtoupper(trim((string) $value));
        if (preg_match('/^M(\d{1,2})$/', $s, $m)) {
            return 'M'.str_pad((string) (int) $m[1], 2, '0', STR_PAD_LEFT);
        }

        return self::csvCell($value);
    }

    private static function csvCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return is_string($value) ? $value : (string) $value;
    }
}
