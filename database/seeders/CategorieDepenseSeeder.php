<?php

namespace Database\Seeders;

use App\Models\CategorieDepense;
use Illuminate\Database\Seeder;

class CategorieDepenseSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['categorie' => 'Matériel', 'code' => 'MAT'],
            ['categorie' => 'Logiciel', 'code' => 'LOG'],
            ['categorie' => 'Services', 'code' => 'SER'],
            ['categorie' => 'Formation', 'code' => 'FOR'],
            ['categorie' => 'Communication', 'code' => 'COM'],
            ['categorie' => 'Logistique', 'code' => 'LOGI'],
            ['categorie' => 'Bâtiment / Travaux', 'code' => 'BAT'],
        ];

        foreach ($items as $item) {
            CategorieDepense::updateOrCreate(
                ['code' => $item['code']],
                $item
            );
        }
    }
}
