<?php

namespace Database\Seeders;

use App\Models\TypologieDepense;
use Illuminate\Database\Seeder;

class TypologieDepenseSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'type' => 'OPEX',
                'libelle' => 'Charges de fonctionnement',
                'description' => 'Dépenses courantes',
            ],
            [
                'type' => 'CAPEX',
                'libelle' => 'Investissements',
                'description' => 'Immobilisations',
            ],
            [
                'type' => 'MNT',
                'libelle' => 'Maintenance',
                'description' => 'Entretien équipements',
            ],
            [
                'type' => 'PRJ',
                'libelle' => 'Projets',
                'description' => 'Projets structurants',
            ],
        ];

        foreach ($items as $item) {
            TypologieDepense::updateOrCreate(
                ['type' => $item['type']],
                $item
            );
        }
    }
}
