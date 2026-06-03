<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class UserTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'Nom',
            'Fonction',
            'Email',
            'IDFLEX',
            'Mot de passe',
            'Role',
            'Departement',
            'Code agence',
        ];
    }

    public function array(): array
    {
        return [
            [
                'Jean Dupont',
                'Responsable RH',
                'jean.dupont@exemple.sn',
                'FLEX001',
                '',
                'demandeur',
                'Ressources Humaines',
                '500',
            ],
            [
                'Marie Ndiaye',
                'Chef agence',
                'marie.ndiaye@exemple.sn',
                'FLEX002',
                'MotDePasse123!',
                'ca',
                '',
                '501',
            ],
        ];
    }
}
