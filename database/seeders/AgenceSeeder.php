<?php

namespace Database\Seeders;

use App\Models\Agence;
use Illuminate\Database\Seeder;

class AgenceSeeder extends Seeder
{
    /**
     * Agences Flex : siège (code {@see Agence::CODE_SIEGE}) puis 501 à 543.
     */
    public function run(): void
    {
        $agences = [
            Agence::CODE_SIEGE => 'Agence siège',
            '501' => 'Agence principale Point E',
            '502' => 'Agence Castors',
            '503' => 'Agence Touba',
            '504' => 'Agence Pikine',
            '505' => 'Agence Mbour',
            '506' => 'Agence Parcelles',
            '507' => 'Agence Kaolack',
            '508' => 'Agence Thiès',
            '509' => 'C-E Nguélaw',
            '510' => 'C-E Rufisque',
            '511' => 'C-E Diourbel',
            '512' => 'C-E Niarry Talli',
            '513' => 'C-E Mbour',
            '514' => 'C-E Touba Ocas',
            '515' => 'C-E Thiès',
            '516' => 'C-E Louga',
            '517' => 'Agence Lamine Guèye',
            '518' => 'C-E Maristes',
            '519' => 'C-E Scat Urbam',
            '520' => 'C-E Pikine',
            '521' => 'C-E Guédiawaye Sahm',
            '522' => 'C-E Ourossogui',
            '523' => 'C-E Tambacounda',
            '524' => 'Agence Saint-Louis',
            '525' => 'Agence Linguerla',
            '526' => 'Agence grands comptes',
            '527' => 'Agence Keur Massar',
            '528' => 'Cofina Express Saint-Louis',
            '529' => 'Cofina Express Guédiawaye',
            '530' => 'Cofina Express Carnot',
            '531' => 'Cofina Express Diamaguène',
            '532' => 'Cofina Express Camberène',
            '533' => 'Cofina Express Yeumbeul 1',
            '534' => 'Cofina Express Yeumbeul 2',
            '535' => 'Cofina Express PA SP',
            '536' => 'Cofina Express Médina',
            '537' => 'Cofina Express Fass',
            '538' => 'Cofina Express Kaolack',
            '539' => 'Cofina Express Sacré-Cœur 3',
            '540' => 'Cofina Express Nord Foire',
            '541' => 'Cofina Express Ouest Foire',
            '542' => 'Cofina Express Keur Massar',
            '543' => 'Agence Ziguinchor',
        ];

        foreach ($agences as $code => $nom) {
            Agence::updateOrCreate(
                ['code' => $code],
                ['nom' => $nom]
            );
        }
    }
}
