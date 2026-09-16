<?php

namespace App\Support;

use App\Models\PodProduit;
use App\Models\PodProduitChamp;
use App\Models\PodProduitConstante;
use App\Models\PodProduitEcran;
use App\Models\PodProduitScript;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Feuilles optionnelles d’un classeur de paramétrage :
 * ECRANS, CHAMPS, SCRIPTS, CONSTANTES (clé CODE_PRODUIT).
 */
final class PodParametrageSheetsImport
{
    /**
     * @return array{ecrans: int, champs: int, scripts: int, constantes: int, errors: list<string>}
     */
    public static function fromSpreadsheet(Spreadsheet $spreadsheet): array
    {
        $stats = ['ecrans' => 0, 'champs' => 0, 'scripts' => 0, 'constantes' => 0, 'errors' => []];

        DB::transaction(function () use ($spreadsheet, &$stats) {
            $stats['ecrans'] += self::importEcrans($spreadsheet, $stats['errors']);
            $stats['scripts'] += self::importScripts($spreadsheet, $stats['errors']);
            $stats['constantes'] += self::importConstantes($spreadsheet, $stats['errors']);
            $stats['champs'] += self::importChamps($spreadsheet, $stats['errors']);
        });

        return $stats;
    }

    /**
     * @param  list<string>  $errors
     */
    private static function importEcrans(Spreadsheet $spreadsheet, array &$errors): int
    {
        $rows = self::sheetRows($spreadsheet, 'ECRANS');
        if ($rows === null) {
            return 0;
        }

        [$header, $data] = $rows;
        $col = self::colFinder($header);
        $iProd = $col('code_produit', 'new_code_produit');
        $iCode = $col('code_ecran', 'code');
        $iLib = $col('libelle', 'nom');
        $iDesc = $col('description');
        $iProfils = $col('profils');

        if ($iProd === null || $iCode === null || $iLib === null) {
            $errors[] = 'Feuille ECRANS : colonnes CODE_PRODUIT, CODE_ECRAN, LIBELLE requises.';

            return 0;
        }

        $count = 0;
        $byProduit = [];

        foreach ($data as $cells) {
            $prodCode = strtoupper(trim((string) ($cells[$iProd] ?? '')));
            $code = strtoupper(trim((string) ($cells[$iCode] ?? '')));
            $libelle = trim((string) ($cells[$iLib] ?? ''));
            if ($prodCode === '' || $code === '' || $libelle === '') {
                continue;
            }
            $byProduit[$prodCode][] = [
                'code' => $code,
                'libelle' => $libelle,
                'description' => $iDesc !== null ? trim((string) ($cells[$iDesc] ?? '')) : null,
                'profils' => self::parseProfils($iProfils !== null ? (string) ($cells[$iProfils] ?? '') : ''),
            ];
        }

        foreach ($byProduit as $prodCode => $ecrans) {
            $produit = PodProduit::query()->where('code', $prodCode)->first();
            if (! $produit) {
                $errors[] = "ECRANS : produit « {$prodCode} » introuvable.";
                continue;
            }
            $produit->champs()->update(['pod_produit_ecran_id' => null]);
            $produit->ecrans()->delete();
            foreach ($ecrans as $order => $e) {
                PodProduitEcran::create([
                    'pod_produit_id' => $produit->id,
                    'code' => $e['code'],
                    'libelle' => $e['libelle'],
                    'description' => $e['description'] ?: null,
                    'profils' => $e['profils'],
                    'actif' => true,
                    'sort_order' => $order,
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  list<string>  $errors
     */
    private static function importChamps(Spreadsheet $spreadsheet, array &$errors): int
    {
        $rows = self::sheetRows($spreadsheet, 'CHAMPS');
        if ($rows === null) {
            return 0;
        }

        [$header, $data] = $rows;
        $col = self::colFinder($header);
        $iProd = $col('code_produit', 'new_code_produit');
        $iCode = $col('code', 'code_champ');
        $iLib = $col('libelle');
        $iType = $col('type');
        $iObl = $col('obligatoire');
        $iVis = $col('visible');
        $iEcran = $col('ecran_code', 'code_ecran');
        $iDef = $col('valeur_defaut', 'defaut');

        if ($iProd === null || $iCode === null || $iLib === null) {
            $errors[] = 'Feuille CHAMPS : colonnes CODE_PRODUIT, CODE, LIBELLE requises.';

            return 0;
        }

        $count = 0;
        $byProduit = [];

        foreach ($data as $cells) {
            $prodCode = strtoupper(trim((string) ($cells[$iProd] ?? '')));
            $code = strtoupper(trim((string) ($cells[$iCode] ?? '')));
            $libelle = trim((string) ($cells[$iLib] ?? ''));
            if ($prodCode === '' || $code === '' || $libelle === '') {
                continue;
            }
            $type = strtolower(trim((string) ($iType !== null ? ($cells[$iType] ?? 'texte') : 'texte')));
            if (! in_array($type, PodProduitChamp::TYPES, true)) {
                $type = PodProduitChamp::TYPE_TEXTE;
            }
            $byProduit[$prodCode][] = [
                'code' => $code,
                'libelle' => $libelle,
                'type' => $type,
                'obligatoire' => self::toBool($iObl !== null ? ($cells[$iObl] ?? false) : false),
                'visible' => self::toBool($iVis !== null ? ($cells[$iVis] ?? true) : true, true),
                'ecran_code' => $iEcran !== null ? strtoupper(trim((string) ($cells[$iEcran] ?? ''))) : '',
                'valeur_defaut' => $iDef !== null ? trim((string) ($cells[$iDef] ?? '')) : null,
            ];
        }

        foreach ($byProduit as $prodCode => $champs) {
            $produit = PodProduit::query()->with('ecrans')->where('code', $prodCode)->first();
            if (! $produit) {
                $errors[] = "CHAMPS : produit « {$prodCode} » introuvable.";
                continue;
            }
            $ecranIds = $produit->ecrans->pluck('id', 'code');
            $produit->champs()->delete();
            foreach ($champs as $order => $c) {
                PodProduitChamp::create([
                    'pod_produit_id' => $produit->id,
                    'pod_produit_ecran_id' => $c['ecran_code'] !== '' ? ($ecranIds[$c['ecran_code']] ?? null) : null,
                    'code' => $c['code'],
                    'libelle' => $c['libelle'],
                    'type' => $c['type'],
                    'obligatoire' => $c['obligatoire'],
                    'visible' => $c['visible'],
                    'gris' => false,
                    'valeur_defaut' => $c['valeur_defaut'] !== '' ? $c['valeur_defaut'] : null,
                    'options' => [],
                    'sort_order' => $order,
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  list<string>  $errors
     */
    private static function importScripts(Spreadsheet $spreadsheet, array &$errors): int
    {
        $rows = self::sheetRows($spreadsheet, 'SCRIPTS');
        if ($rows === null) {
            return 0;
        }

        [$header, $data] = $rows;
        $col = self::colFinder($header);
        $iProd = $col('code_produit', 'new_code_produit');
        $iCode = $col('code', 'code_script');
        $iLib = $col('libelle');
        $iMoteur = $col('moteur');
        $iVal = $col('param_valeur', 'valeur');
        $iTaux = $col('param_taux', 'taux');
        $iChamp = $col('param_champ', 'champ');

        if ($iProd === null || $iCode === null || $iLib === null || $iMoteur === null) {
            $errors[] = 'Feuille SCRIPTS : CODE_PRODUIT, CODE, LIBELLE, MOTEUR requis.';

            return 0;
        }

        $count = 0;
        $byProduit = [];

        foreach ($data as $cells) {
            $prodCode = strtoupper(trim((string) ($cells[$iProd] ?? '')));
            $code = strtoupper(trim((string) ($cells[$iCode] ?? '')));
            $libelle = trim((string) ($cells[$iLib] ?? ''));
            $moteur = trim((string) ($cells[$iMoteur] ?? ''));
            if ($prodCode === '' || $code === '' || $libelle === '' || $moteur === '') {
                continue;
            }
            if (! isset(PodScriptRegistry::MOTEURS[$moteur])) {
                $errors[] = "SCRIPTS : moteur « {$moteur} » inconnu (ligne {$code}).";
                continue;
            }
            $params = [];
            if ($iVal !== null && trim((string) ($cells[$iVal] ?? '')) !== '') {
                $params['valeur'] = trim((string) $cells[$iVal]);
            }
            if ($iTaux !== null && trim((string) ($cells[$iTaux] ?? '')) !== '') {
                $params['taux'] = trim((string) $cells[$iTaux]);
            }
            if ($iChamp !== null && trim((string) ($cells[$iChamp] ?? '')) !== '') {
                $params['champ'] = strtoupper(trim((string) $cells[$iChamp]));
            }
            $byProduit[$prodCode][] = compact('code', 'libelle', 'moteur') + ['parametres' => $params];
        }

        foreach ($byProduit as $prodCode => $scripts) {
            $produit = PodProduit::query()->where('code', $prodCode)->first();
            if (! $produit) {
                $errors[] = "SCRIPTS : produit « {$prodCode} » introuvable.";
                continue;
            }
            $produit->scripts()->delete();
            foreach ($scripts as $order => $s) {
                PodProduitScript::create([
                    'pod_produit_id' => $produit->id,
                    'code' => $s['code'],
                    'libelle' => $s['libelle'],
                    'moteur' => $s['moteur'],
                    'parametres' => $s['parametres'],
                    'actif' => true,
                    'sort_order' => $order,
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  list<string>  $errors
     */
    private static function importConstantes(Spreadsheet $spreadsheet, array &$errors): int
    {
        $rows = self::sheetRows($spreadsheet, 'CONSTANTES');
        if ($rows === null) {
            return 0;
        }

        [$header, $data] = $rows;
        $col = self::colFinder($header);
        $iProd = $col('code_produit', 'new_code_produit');
        $iCode = $col('code', 'code_constante');
        $iLib = $col('libelle');
        $iType = $col('type');
        $iMode = $col('mode');
        $iRef = $col('valeur_reference', 'reference', 'valeur');
        $iObl = $col('obligatoire');

        if ($iProd === null || $iCode === null || $iLib === null || $iMode === null) {
            $errors[] = 'Feuille CONSTANTES : CODE_PRODUIT, CODE, LIBELLE, MODE requis.';

            return 0;
        }

        $count = 0;
        $byProduit = [];

        foreach ($data as $cells) {
            $prodCode = strtoupper(trim((string) ($cells[$iProd] ?? '')));
            $code = strtoupper(trim((string) ($cells[$iCode] ?? '')));
            $libelle = trim((string) ($cells[$iLib] ?? ''));
            $mode = strtolower(trim((string) ($cells[$iMode] ?? 'defaut')));
            if ($prodCode === '' || $code === '' || $libelle === '') {
                continue;
            }
            if (! in_array($mode, PodProduitConstante::MODES, true)) {
                $mode = PodProduitConstante::MODE_DEFAUT;
            }
            $type = strtolower(trim((string) ($iType !== null ? ($cells[$iType] ?? 'texte') : 'texte')));
            if (! in_array($type, PodProduitConstante::TYPES, true)) {
                $type = 'texte';
            }
            $byProduit[$prodCode][] = [
                'code' => $code,
                'libelle' => $libelle,
                'type' => $type,
                'mode' => $mode,
                'valeur_reference' => $iRef !== null ? trim((string) ($cells[$iRef] ?? '')) : null,
                'obligatoire' => self::toBool($iObl !== null ? ($cells[$iObl] ?? false) : false),
            ];
        }

        foreach ($byProduit as $prodCode => $constantes) {
            $produit = PodProduit::query()->where('code', $prodCode)->first();
            if (! $produit) {
                $errors[] = "CONSTANTES : produit « {$prodCode} » introuvable.";
                continue;
            }
            $produit->constantes()->delete();
            foreach ($constantes as $order => $c) {
                PodProduitConstante::create([
                    'pod_produit_id' => $produit->id,
                    'code' => $c['code'],
                    'libelle' => $c['libelle'],
                    'type' => $c['type'],
                    'mode' => $c['mode'],
                    'valeur_reference' => $c['valeur_reference'] !== '' ? $c['valeur_reference'] : null,
                    'obligatoire' => $c['obligatoire'],
                    'sort_order' => $order,
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array{0: list<string>, 1: list<list<mixed>}|null}|null
     */
    private static function sheetRows(Spreadsheet $spreadsheet, string $name): ?array
    {
        $sheet = $spreadsheet->getSheetByName($name);
        if (! $sheet) {
            return null;
        }
        $rows = $sheet->toArray(null, true, false, false);
        if ($rows === []) {
            return null;
        }
        $header = array_map(static fn ($v) => self::normalizeHeader((string) ($v ?? '')), $rows[0] ?? []);

        return [$header, array_slice($rows, 1)];
    }

    /**
     * @param  list<string>  $header
     * @return callable(string ...): (?int)
     */
    private static function colFinder(array $header): callable
    {
        return static function (string ...$names) use ($header): ?int {
            foreach ($names as $name) {
                foreach ($header as $i => $h) {
                    if ($h === $name) {
                        return $i;
                    }
                }
            }

            return null;
        };
    }

    private static function normalizeHeader(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $value = str_replace(['é', 'è', 'ê', 'à', 'ù', 'ô', 'î', 'ç'], ['e', 'e', 'e', 'a', 'u', 'o', 'i', 'c'], $value);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? $value;

        return trim($value, '_');
    }

    /**
     * @return list<string>
     */
    private static function parseProfils(string $raw): array
    {
        $keys = array_keys(PodProduitEcran::PROFILS);
        $parts = preg_split('/[,;|]+/', $raw) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = strtolower(trim($p));
            $p = str_replace([' ', '-'], '_', $p);
            if (in_array($p, $keys, true)) {
                $out[] = $p;
            }
            if ($p === 'finance_it' || $p === 'finance/it') {
                $out[] = 'finance';
            }
        }

        return array_values(array_unique($out));
    }

    private static function toBool(mixed $value, bool $default = false): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }
        if (is_bool($value)) {
            return $value;
        }
        $v = strtolower(trim((string) $value));

        return in_array($v, ['1', 'true', 'oui', 'yes', 'o', 'y'], true);
    }
}
