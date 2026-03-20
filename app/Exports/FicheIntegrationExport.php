<?php

namespace App\Exports;

use App\Models\FicheIntegration;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class FicheIntegrationExport implements FromCollection, WithHeadings, WithMapping
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return FicheIntegration::all();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Numéro',
            'N° Batch',
            'N° Compte',
            'Sens (D/C)',
            'Montant',
            'Code Opération',
            'Date de Valeur',
            'Code Agence',
            'Libellé écriture',
            'Année Comptable',
            'Mois Comptable',
            'Montant à Payer',
            'Account (Montant)',
            'Relicat',
            'Restant à Payer',
            'Statut',
            'Créé le'
        ];
    }

    public function map($fiche): array
    {
        return [
            $fiche->id,
            $fiche->numero,
            $fiche->no_batch,
            $fiche->no_compte,
            $fiche->sens,
            $fiche->montant,
            $fiche->code_operation,
            $fiche->date_de_valeur,
            $fiche->code_agence,
            $fiche->libele_ecriture,
            $fiche->annee_comptable,
            $fiche->mois_comptable,
            $fiche->montantAPayer,
            $fiche->account,
            $fiche->relicat,
            $fiche->restantAPayer,
            $fiche->statut,
            $fiche->created_at ? $fiche->created_at->format('Y-m-d H:i:s') : '',
        ];
    }
}
