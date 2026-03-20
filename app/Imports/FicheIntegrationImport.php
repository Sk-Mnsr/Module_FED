<?php

namespace App\Imports;

use App\Models\FicheIntegration;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class FicheIntegrationImport implements ToModel, WithHeadingRow
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        // Nettoyage de date via strtotime (Excel stocke parfois différemment, adapter si nécessaire)
        $dateValeur = isset($row['date_de_valeur']) ? date('Y-m-d', strtotime($row['date_de_valeur'])) : now()->format('Y-m-d');
        
        return new FicheIntegration([
            'numero'         => $row['numero'] ?? 'EXCEL-' . rand(1000, 9999),
            'no_batch'       => $row['no_batch'] ?? 'BATCH-' . rand(1000, 9999),
            'no_compte'      => $row['no_compte'] ?? '0000',
            'sens'           => $row['sens_dc'] ?? $row['sens'] ?? 'D',
            'montant'        => isset($row['montant']) ? floatval($row['montant']) : 0,
            'code_operation' => isset($row['code_operation']) ? intval($row['code_operation']) : 0,
            'date_de_valeur' => $dateValeur,
            'code_agence'    => isset($row['code_agence']) ? intval($row['code_agence']) : 500,
            'libele_ecriture'=> $row['libelle_ecriture'] ?? $row['libele'] ?? 'Import Auto',
            'annee_comptable'=> $row['annee_comptable'] ?? now()->format('Y'),
            'mois_comptable' => $row['mois_comptable'] ?? now()->format('m'),
            'montantAPayer'  => isset($row['montant_a_payer']) ? floatval($row['montant_a_payer']) : 0,
            'account'        => isset($row['account_montant']) ? floatval($row['account_montant']) : 0,
            'relicat'        => isset($row['relicat']) ? floatval($row['relicat']) : 0,
            'restantAPayer'  => isset($row['restant_a_payer']) ? floatval($row['restant_a_payer']) : 0,
            'statut'         => $row['statut'] ?? 'en_attente',
            'user_id'        => auth()->id() ?? '1',
        ]);
    }
}
