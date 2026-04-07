<?php

namespace App\Imports;

use App\Models\Famille;
use App\Models\Categorie;
use App\Models\SousCategorie;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class FamilleImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $row = $row->toArray();
            
            // Ignorer les lignes vides
            if (empty($row['famille']) && empty($row['categorie']) && empty($row['sous_categorie'])) {
                continue;
            }

            // Gestion de la famille
            $famille = null;
            if (!empty($row['famille'])) {
                $famille = Famille::firstOrCreate([
                    'nom' => trim($row['famille'])
                ]);
            }

            // Gestion de la catégorie
            $categorie = null;
            if ($famille && !empty($row['categorie'])) {
                $categorie = Categorie::firstOrCreate([
                    'nom' => trim($row['categorie']),
                    'famille_id' => $famille->id
                ]);
            }

            // Gestion de la sous-catégorie
            if ($categorie && !empty($row['sous_categorie'])) {
                SousCategorie::firstOrCreate([
                    'nom' => trim($row['sous_categorie']),
                    'categorie_id' => $categorie->id
                ]);
            }
        }
    }
}
