<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class FamilleTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'Famille',
            'Categorie',
            'Sous_categorie'
        ];
    }

    public function array(): array
    {
        return [
            ['Informatique', 'Hardware', 'Laptops'],
            ['Informatique', 'Hardware', 'Écrans'],
            ['Informatique', 'Software', 'Licences'],
            ['Fournitures', 'Bureau', 'Stylos'],
        ];
    }
}
