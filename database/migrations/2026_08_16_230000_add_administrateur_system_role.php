<?php

use App\Models\Role;
use App\Support\ModuleAccess;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $role = Role::query()->updateOrCreate(
            ['slug' => 'administrateur'],
            [
                'nom' => 'Administrateur',
                'module' => 'administration',
                'access_profile' => 'other',
                'description' => 'Administration système uniquement : utilisateurs, rôles, départements, agences, articles, familles, apporteurs, paramètres. Sans accès automatique aux modules métier.',
                'actif' => true,
            ],
        );

        if (Schema::hasTable('role_module')) {
            $exists = DB::table('role_module')
                ->where('role_id', $role->id)
                ->where('module', 'administration')
                ->exists();

            if (! $exists) {
                DB::table('role_module')->insert([
                    'role_id' => $role->id,
                    'module' => 'administration',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Role::query()->where('slug', 'it')->update([
            'description' => 'SuperAdmin (IT) — tous les modules métier + administration système (bypass)',
        ]);

        ModuleAccess::clearModuleRolesCache();
    }

    public function down(): void
    {
        $role = Role::query()->where('slug', 'administrateur')->first();
        if ($role === null) {
            return;
        }

        if (Schema::hasTable('role_module')) {
            DB::table('role_module')->where('role_id', $role->id)->delete();
        }

        if (! $role->users()->exists()) {
            $role->delete();
        }

        ModuleAccess::clearModuleRolesCache();
    }
};
