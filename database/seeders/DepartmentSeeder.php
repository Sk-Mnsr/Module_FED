<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['name' => 'Informatique', 'code' => 'IT'],
            ['name' => 'Facilities / Moyens Généraux', 'code' => 'FAC'],
            ['name' => 'Crédit', 'code' => 'CRE'],
            ['name' => 'Marketing', 'code' => 'MKT'],
            ['name' => 'Communication', 'code' => 'COM'],
            ['name' => 'Opérations', 'code' => 'OPS'],
            ['name' => 'Juridique', 'code' => 'JUR'],
            ['name' => 'Ressources Humaines', 'code' => 'RH'],
            ['name' => 'Innovation', 'code' => 'INN'],
            ['name' => 'Autres', 'code' => 'AUT'],
        ];

        foreach ($departments as $department) {
            Department::firstOrCreate(
                ['code' => $department['code']],
                ['name' => $department['name']]
            );
        }
    }
}
