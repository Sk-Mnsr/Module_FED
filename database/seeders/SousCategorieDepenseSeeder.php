<?php

namespace Database\Seeders;

use App\Models\CategorieDepense;
use App\Models\SousCategorieDepense;
use Illuminate\Database\Seeder;

class SousCategorieDepenseSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'MAT' => ['Ordinateur' => 'ORD', 'Écran' => 'ECR', 'Imprimante' => 'IMP', 'Téléphone' => 'TEL', 'Autre' => 'AUT'],
            'LOG' => ['Licence' => 'LIC', 'SaaS' => 'SAA', 'Maintenance' => 'MNT', 'Autre' => 'AUT'],
            'SER' => ['Consulting' => 'CON', 'Support' => 'SUP', 'Prestation' => 'PRE', 'Autre' => 'AUT'],
            'FOR' => ['Interne' => 'INT', 'Externe' => 'EXT', 'Certification' => 'CER', 'Autre' => 'AUT'],
            'COM' => ['Téléphonie' => 'TEL', 'Internet' => 'INT', 'Abonnement' => 'ABO', 'Autre' => 'AUT'],
            'LOGI' => ['Fournitures' => 'FOU', 'Électricité' => 'ELC', 'Eau' => 'EAU', 'Autre' => 'AUT'],
            'BAT' => ['Travaux' => 'TRA', 'Rénovation' => 'REN', 'Électricité' => 'ELC', 'Autre' => 'AUT'],
        ];

        foreach ($data as $catCode => $sousCategories) {
            $categorie = CategorieDepense::where('code', $catCode)->first();
            if (!$categorie) {
                continue;
            }
            foreach ($sousCategories as $libelle => $code) {
                SousCategorieDepense::updateOrCreate(
                    [
                        'categorie_depense_id' => $categorie->id,
                        'code' => $code,
                    ],
                    ['sous_categorie' => $libelle]
                );
            }
        }
    }
}
