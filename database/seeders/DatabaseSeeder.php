<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            AgenceSeeder::class,
            DepartmentSeeder::class,
            TypologieDepenseSeeder::class,
            CategorieDepenseSeeder::class,
            SousCategorieDepenseSeeder::class,
        ]);

        // Idempotent : ne plante pas si l’email existe déjà
        $user = User::query()->updateOrCreate(
            ['email' => 'mansour.seck@cofinacorp.com'],
            [
                'name' => 'Mansour SECK',
                'fonction' => 'Agent IT',
                'password' => 'Cofina@123', // cast « hashed » sur le modèle
                'profile' => 'admin',
                'password_change_required' => false,
                'activated' => true,
            ],
        );

        $superAdminRole = Role::query()->where('slug', 'it')->first();
        if ($superAdminRole) {
            $user->roles()->syncWithoutDetaching([$superAdminRole->id]);
        }
    }
}
