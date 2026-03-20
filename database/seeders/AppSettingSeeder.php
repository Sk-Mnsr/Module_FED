<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class AppSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key'         => 'fed_dga_threshold',
                'value'       => '10000000',
                'label'       => 'Seuil de validation DGA (XOF)',
                'description' => 'Si le montant total de la FED dépasse ce seuil, elle sera transmise au DGA pour validation finale avant la génération du bon de commande. Mettre 0 pour désactiver le passage par le DGA.',
                'type'        => 'number',
            ],
        ];

        foreach ($settings as $setting) {
            AppSetting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
