<?php

namespace Database\Seeders;

use App\Models\Role;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call([
            RoleSeeder::class,
            AgenceSeeder::class,
            DepartmentSeeder::class,
            TypologieDepenseSeeder::class,
            CategorieDepenseSeeder::class,
            SousCategorieDepenseSeeder::class,
        ]);

        $user = User::factory()->create([
            'name' => 'Mansour SECK',
            'email' => 'mansour.seck@cofinacorp.com',
            'fonction' => 'Agent IT',
            'password' => Hash::make('Cofina@123'),
            'profile' => 'admin',
            'password_change_required' => false,
        ]);
        $user->roles()->attach(Role::where('slug', 'it')->first()->id);

        $cgRole = Role::where('slug', 'controle_de_gestion')->first();
        if ($cgRole) {
            $cgUser = User::firstOrCreate(
                ['email' => 'cg@cofinacorp.com'],
                [
                    'name' => 'Contrôle de Gestion',
                    'fonction' => 'Contrôleur de Gestion',
                    'password' => Hash::make('Cofina@123'),
                    'profile' => 'other',
                    'password_change_required' => false,
                ]
            );
            $cgUser->roles()->syncWithoutDetaching([$cgRole->id]);
        }
    }
}
