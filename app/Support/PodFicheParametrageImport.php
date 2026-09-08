<?php

namespace App\Support;

use App\Models\PodLigneComptable;
use App\Models\PodProduit;
use App\Models\PodTrancheFrais;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Import de la fiche Excel de paramétrage POD (Sheet1).
 */
final class PodFicheParametrageImport
{
    /**
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    public static function fromPath(string $path, ?User $user = null): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $e) {
            $stats['errors'][] = 'Lecture Excel impossible : '.$e->getMessage();

            return $stats;
        }

        $sheet = $spreadsheet->getSheetByName('Sheet1') ?? $spreadsheet->getSheet(0);
        $rows = $sheet->toArray(null, true, false, false);

        if ($rows === []) {
            $stats['errors'][] = 'Feuille vide.';

            return $stats;
        }

        $header = array_map(
            static fn ($v) => self::normalizeHeader((string) ($v ?? '')),
            $rows[0] ?? []
        );

        $col = static function (string ...$names) use ($header): ?int {
            foreach ($names as $name) {
                foreach ($header as $i => $h) {
                    if ($h === $name) {
                        return $i;
                    }
                }
            }

            return null;
        };

        // La fiche métier contient parfois la typo « OEPRATION ».
        $iOp = $col('operation', 'oepration');
        $iPrix = $col('prix_unitaire');
        $iCompte = $col('compte_produit');
        $iCode = $col('new_code_produit', 'code_produit');
        $iLibProd = $col('libelle_ecriture_produit');
        $iCodeTaf = $col('new_code_taf', 'code_taf');
        $iLibTaf = $col('libelle_ecriture_taf');
        $iInit = $col('initiateur');

        if ($iOp === null || $iCode === null) {
            $stats['errors'][] = 'Colonnes obligatoires manquantes (OPERATION, NEW CODE PRODUIT).';

            return $stats;
        }

        /** @var array<string, list<array<string, mixed>>> $grouped */
        $grouped = [];

        foreach (array_slice($rows, 1) as $lineNum => $cells) {
            $code = trim((string) ($cells[$iCode] ?? ''));
            $operation = trim((string) ($cells[$iOp] ?? ''));

            if ($code === '' && $operation === '') {
                continue;
            }

            if ($code === '') {
                $stats['skipped']++;
                $stats['errors'][] = 'Ligne '.($lineNum + 2).' : code produit manquant.';

                continue;
            }

            $grouped[$code][] = [
                'operation' => $operation,
                'prix' => trim((string) ($cells[$iPrix] ?? '')),
                'compte_produit' => trim((string) ($cells[$iCompte] ?? '')),
                'libelle_produit' => trim((string) ($cells[$iLibProd] ?? '')),
                'code_taf' => trim((string) ($cells[$iCodeTaf] ?? '')),
                'libelle_taf' => trim((string) ($cells[$iLibTaf] ?? '')),
                'initiateur' => trim((string) ($cells[$iInit] ?? '')),
            ];
        }

        DB::transaction(function () use ($grouped, $user, &$stats) {
            foreach ($grouped as $code => $entries) {
                $first = $entries[0];
                $parsedTranches = [];
                $numericPrices = [];
                $textRules = [];

                foreach ($entries as $entry) {
                    $tranche = self::parsePrixAndTranche($entry['operation'], $entry['prix']);
                    if ($tranche['is_tranche']) {
                        $parsedTranches[] = $tranche;
                    } elseif ($tranche['frais_fixe'] !== null) {
                        $numericPrices[] = $tranche['frais_fixe'];
                    } elseif ($tranche['rule_text'] !== null && $tranche['rule_text'] !== '') {
                        $textRules[] = $tranche['rule_text'];
                    }
                }

                $mode = PodProduit::MODE_FIXE;
                $fraisFixe = null;
                $baseCalcul = null;

                if (count($parsedTranches) > 1 || (count($parsedTranches) === 1 && count($entries) > 1)) {
                    $mode = PodProduit::MODE_TRANCHE;
                } elseif ($numericPrices !== []) {
                    $mode = PodProduit::MODE_FIXE;
                    $fraisFixe = $numericPrices[0];
                    if (abs((float) $fraisFixe) < 0.00001) {
                        $mode = PodProduit::MODE_GRATUIT;
                    }
                } elseif ($textRules !== []) {
                    $mode = PodProduit::MODE_MANUEL;
                    $baseCalcul = implode(' | ', array_unique($textRules));
                } else {
                    $mode = PodProduit::MODE_MANUEL;
                    $baseCalcul = $first['prix'] !== '' ? $first['prix'] : null;
                }

                $libelle = $first['libelle_produit'] !== ''
                    ? $first['libelle_produit']
                    : self::cleanOperationLabel($first['operation']);

                $payload = [
                    'libelle' => $libelle,
                    'type_operation' => self::guessType($first['operation']),
                    'devise' => 'XOF',
                    'code_taf' => $first['code_taf'] !== '' ? $first['code_taf'] : null,
                    'libelle_ecriture_produit' => $first['libelle_produit'] !== '' ? $first['libelle_produit'] : $libelle,
                    'libelle_ecriture_taf' => $first['libelle_taf'] !== '' ? $first['libelle_taf'] : null,
                    'compte_produit' => $first['compte_produit'] !== '' ? $first['compte_produit'] : null,
                    'compte_taf' => '331431012',
                    'compte_client_mask' => '251XXXXXX',
                    'initiateur' => $first['initiateur'] !== '' ? strtoupper($first['initiateur']) : null,
                    'mode_frais' => $mode,
                    'frais_fixe' => $fraisFixe,
                    'base_calcul' => $baseCalcul,
                    'statut' => PodProduit::STATUT_BROUILLON,
                    'actif' => true,
                    'updated_by_user_id' => $user?->id,
                ];

                $existing = PodProduit::query()->where('code', $code)->first();

                if ($existing) {
                    $existing->update($payload);
                    $produit = $existing;
                    $stats['updated']++;
                } else {
                    $payload['code'] = $code;
                    $payload['created_by_user_id'] = $user?->id;
                    $produit = PodProduit::create($payload);
                    $stats['created']++;
                }

                $produit->tranches()->delete();
                $produit->lignesComptables()->delete();

                if ($mode === PodProduit::MODE_TRANCHE) {
                    foreach ($parsedTranches as $order => $tranche) {
                        PodTrancheFrais::create([
                            'pod_produit_id' => $produit->id,
                            'libelle' => $tranche['libelle'],
                            'montant_min' => $tranche['montant_min'],
                            'montant_max' => $tranche['montant_max'],
                            'frais_fixe' => $tranche['frais_fixe'],
                            'sort_order' => $order,
                        ]);
                    }
                }

                self::seedDefaultSchema($produit);
            }
        });

        return $stats;
    }

    private static function seedDefaultSchema(PodProduit $produit): void
    {
        $compteClient = $produit->compte_client_mask ?: '251XXXXXX';
        $compteProduit = $produit->compte_produit ?: '7XXXXXXX';
        $compteTaf = $produit->compte_taf ?: '331431012';
        $libProd = $produit->libelle_ecriture_produit ?: $produit->libelle;
        $libTaf = $produit->libelle_ecriture_taf ?: ('TAF '.$produit->libelle);

        $lines = [
            ['sens' => 'D', 'compte' => $compteClient, 'libelle_ecriture' => $libProd.' HT', 'nature_compte' => 'client', 'type_montant' => 'frais_ht'],
            ['sens' => 'D', 'compte' => $compteClient, 'libelle_ecriture' => $libTaf, 'nature_compte' => 'client', 'type_montant' => 'taf'],
            ['sens' => 'C', 'compte' => $compteProduit, 'libelle_ecriture' => $libProd.' HT', 'nature_compte' => 'produit', 'type_montant' => 'frais_ht'],
            ['sens' => 'C', 'compte' => $compteTaf, 'libelle_ecriture' => $libTaf, 'nature_compte' => 'taf', 'type_montant' => 'taf'],
        ];

        if ($produit->mode_frais === PodProduit::MODE_GRATUIT) {
            return;
        }

        foreach ($lines as $order => $line) {
            PodLigneComptable::create([
                'pod_produit_id' => $produit->id,
                'sort_order' => $order,
                ...$line,
                'obligatoire' => true,
            ]);
        }
    }

    /**
     * @return array{
     *   is_tranche: bool,
     *   libelle: ?string,
     *   montant_min: float,
     *   montant_max: ?float,
     *   frais_fixe: ?float,
     *   rule_text: ?string
     * }
     */
    private static function parsePrixAndTranche(string $operation, string $prix): array
    {
        $empty = [
            'is_tranche' => false,
            'libelle' => null,
            'montant_min' => 0.0,
            'montant_max' => null,
            'frais_fixe' => null,
            'rule_text' => null,
        ];

        $numeric = self::toFloat($prix);
        if ($numeric !== null) {
            $range = self::extractRangeFromLabel($operation);
            if ($range !== null) {
                return [
                    'is_tranche' => true,
                    'libelle' => self::cleanOperationLabel($operation),
                    'montant_min' => $range['min'],
                    'montant_max' => $range['max'],
                    'frais_fixe' => $numeric,
                    'rule_text' => null,
                ];
            }

            return [...$empty, 'frais_fixe' => $numeric];
        }

        $upper = mb_strtoupper($prix);
        if (str_contains($upper, 'GRATUIT')) {
            return [...$empty, 'frais_fixe' => 0.0];
        }

        return [...$empty, 'rule_text' => $prix !== '' ? $prix : null];
    }

    /**
     * @return array{min: float, max: ?float}|null
     */
    private static function extractRangeFromLabel(string $label): ?array
    {
        $normalized = str_replace(["\xc2\xa0", ' '], '', $label);
        $normalized = str_ireplace(['FCFA', 'XOF'], '', $normalized);

        if (preg_match('/De\s*([\d\s\.]+)\s*à\s*([\d\s\.]+)/iu', $label, $m)) {
            return [
                'min' => (float) preg_replace('/\D/', '', $m[1]),
                'max' => (float) preg_replace('/\D/', '', $m[2]),
            ];
        }

        if (preg_match('/A\s*partir\s*de\s*([\d\s\.]+)/iu', $label, $m)
            || preg_match('/>\s*([\d\s\.]+)/u', $normalized, $m)) {
            return [
                'min' => (float) preg_replace('/\D/', '', $m[1]),
                'max' => null,
            ];
        }

        if (preg_match('/([\d\s\.]+)\s*à\s*([\d\s\.]+)/u', $label, $m)) {
            return [
                'min' => (float) preg_replace('/\D/', '', $m[1]),
                'max' => (float) preg_replace('/\D/', '', $m[2]),
            ];
        }

        return null;
    }

    private static function toFloat(string $value): ?float
    {
        $value = trim(str_replace(["\xc2\xa0", ' '], '', $value));
        if ($value === '' || ! preg_match('/^-?[\d]+([.,]\d+)?$/', $value)) {
            return null;
        }

        return (float) str_replace(',', '.', $value);
    }

    private static function cleanOperationLabel(string $label): string
    {
        $label = preg_replace('/\s*\((Personne\s+(Morale|Physique))\)\s*/iu', ' ($1) ', $label) ?? $label;
        $label = preg_replace('/\s+(De|A partir de|>).*$/iu', '', $label) ?? $label;

        return trim($label);
    }

    private static function guessType(string $operation): ?string
    {
        $u = mb_strtoupper($operation);
        if (str_contains($u, 'CREDIT') || str_contains($u, 'PRÊT') || str_contains($u, 'PRET')) {
            return 'Frais de demande de crédit';
        }
        if (str_contains($u, 'PACK')) {
            return 'Changement de pack';
        }
        if (str_contains($u, 'VIREMENT')) {
            return 'Commission virement';
        }
        if (str_contains($u, 'CHEQUE') || str_contains($u, 'CHÈQUE')) {
            return 'Opération chèque';
        }

        return 'Opération diverse';
    }

    private static function normalizeHeader(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $value = strtr($value, [
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'ù' => 'u', 'ô' => 'o', 'î' => 'i', 'ç' => 'c',
        ]);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? $value;

        return trim($value, '_');
    }
}
