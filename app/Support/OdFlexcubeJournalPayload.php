<?php

namespace App\Support;

use App\Models\OdClasseur;
use Illuminate\Support\Carbon;

/**
 * Construit le corps JSON CreateMjrnlbook à partir d’un classeur OD / CSV parsé.
 */
final class OdFlexcubeJournalPayload
{
    /**
     * @param  array{
     *     rows: list<array<string, mixed>>,
     *     total_debit?: float,
     *     total_credit?: float,
     *     devise?: string,
     *     error?: string|null
     * }  $parsed
     * @return array<string, mixed>
     */
    public static function fromParsed(OdClasseur $classeur, array $parsed, ?string $maker = null): array
    {
        $cfg = config('services.flexcube_online_journal', []);
        $defaultCcy = (string) ($cfg['ccy'] ?? 'XOF');
        $defaultBranch = (string) ($cfg['branch'] ?? '501');
        $accorgl = (string) ($cfg['accorgl'] ?? 'A');
        $defaultTxn = (string) ($cfg['txn_code_default'] ?? 'MIG');
        $lastOperatedBy = (string) ($cfg['last_operated_by'] ?? 'APIUSER1');

        $rows = $parsed['rows'] ?? [];
        if ($rows === []) {
            throw new \InvalidArgumentException('Aucune écriture à transmettre à Flexcube.');
        }

        $first = $rows[0];
        $branch = self::nonEmpty((string) ($first['code_agence'] ?? ''), $defaultBranch);
        $ccy = self::nonEmpty((string) ($parsed['devise'] ?? ''), $defaultCcy);
        $valueDate = self::toIsoDate(
            (string) ($first['date_de_valeur'] ?? ''),
            $classeur->date_valeur,
        );

        $description = trim((string) ($classeur->nom_classeur ?: 'OD'));
        // Flexcube attribue le n° de batch : on envoie vide.
        $batchNo = '';
        $makerId = trim((string) ($maker ?? $lastOperatedBy));

        $details = [];
        $totalDr = 0.0;
        $totalCr = 0.0;
        $serial = 1;

        foreach ($rows as $row) {
            $sens = strtoupper(substr((string) ($row['_sens'] ?? $row['sens'] ?? ''), 0, 1));
            if (! in_array($sens, ['D', 'C'], true)) {
                continue;
            }

            $amount = (float) ($row['_montant'] ?? $row['montant'] ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $lineBranch = self::nonEmpty((string) ($row['code_agence'] ?? ''), $branch);
            $txnCode = self::nonEmpty((string) ($row['code_operation'] ?? ''), $defaultTxn);
            $account = trim((string) ($row['no_compte'] ?? ''));
            $text = trim((string) ($row['libelle_ecriture'] ?? ''));

            if ($account === '') {
                throw new \InvalidArgumentException("Ligne {$serial} : compte manquant.");
            }

            if ($sens === 'D') {
                $totalDr += $amount;
            } else {
                $totalCr += $amount;
            }

            $details[] = [
                'referenceNo' => '',
                'serialNo' => $serial,
                'userRefNo' => '',
                'drCr' => $sens,
                'branchCode' => $lineBranch,
                'accorgl' => $accorgl,
                'ccy' => $ccy,
                'amount' => $amount,
                'txnCode' => $txnCode,
                'instrumentNo' => '',
                'lcyAmount' => $amount,
                'addlText' => $text,
                'acdesc' => '',
                'customer' => '',
                'exchRate' => 1,
                'account' => $account,
            ];

            $serial++;
        }

        if ($details === []) {
            throw new \InvalidArgumentException('Aucune ligne Débit/Crédit valide à transmettre.');
        }

        return [
            'referenceNo' => '',
            'batchNo' => $batchNo,
            'currNo' => count($details),
            'templateCode' => '',
            'valueDate' => $valueDate,
            'bookDate' => $valueDate,
            'branchCode' => $branch,
            'ccy' => $ccy,
            'totalDr' => $totalDr,
            'totalCr' => $totalCr,
            'fetch' => '',
            'maker' => $makerId,
            'makdttime' => '',
            'chechkerid' => '',
            'chkdttime' => '',
            'authstat' => 'U',
            'txnstat' => 'U',
            'btnBatSum' => '',
            'mis' => '',
            'fundid' => '',
            'btnNext' => '',
            'of' => '',
            'record' => '',
            'recNo' => 0,
            'totalNo' => 0,
            'subsysstat' => '',
            'deFields' => '',
            'detbsJrnlTxnDetailList' => $details,
            'detbsBatchMaster' => [
                'batchNo' => $batchNo,
                'description' => $description,
                'debit' => $totalDr,
                'credit' => $totalCr,
                'drEntTotal' => $totalDr,
                'crEntTotal' => $totalCr,
                'btncomp' => '',
            ],
            'devwsBatchMaster' => [
                'branchCode' => $branch,
                'batchNumber' => $batchNo,
                'description' => $description,
                'type' => '',
                'lastOperatedBy' => $makerId !== '' ? $makerId : $lastOperatedBy,
                'lastAuthorisedBy' => '',
                'makerdt' => '',
                'checkerdt' => '',
                'locked' => 'Y',
                'currNo' => 0,
                'debit' => $totalDr,
                'credit' => $totalCr,
                'authStat' => '',
                'uploaded' => '',
                'balancing' => 'Y',
                'sys1' => '',
                'position' => '',
                'status' => '',
            ],
        ];
    }

    private static function nonEmpty(string $value, string $fallback): string
    {
        $value = trim($value);

        return $value !== '' ? $value : $fallback;
    }

    private static function toIsoDate(string $raw, mixed $fallbackDate): string
    {
        $raw = trim($raw);
        if ($raw !== '') {
            foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
                try {
                    return Carbon::createFromFormat($format, $raw)->format('Y-m-d');
                } catch (\Throwable) {
                    // try next
                }
            }

            try {
                return Carbon::parse($raw)->format('Y-m-d');
            } catch (\Throwable) {
                // fallback below
            }
        }

        if ($fallbackDate instanceof Carbon) {
            return $fallbackDate->format('Y-m-d');
        }

        if (is_string($fallbackDate) && trim($fallbackDate) !== '') {
            try {
                return Carbon::parse($fallbackDate)->format('Y-m-d');
            } catch (\Throwable) {
                // ignore
            }
        }

        return now()->format('Y-m-d');
    }
}
