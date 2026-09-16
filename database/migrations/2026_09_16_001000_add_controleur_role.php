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
        // Renomme l’éventuel ancien slug controleur_od → controleur
        $legacy = Role::query()->where('slug', 'controleur_od')->first();
        if ($legacy !== null) {
            $legacy->update([
                'slug' => 'controleur',
                'nom' => 'Contrôleur',
            ]);
        }

        $role = Role::query()->updateOrCreate(
            ['slug' => 'controleur'],
            [
                'nom' => 'Contrôleur',
                'module' => 'od',
                'access_profile' => 'other',
                'description' => 'Contrôle final des pièces archivées (justificatifs + pièce comptable). Pas d’intégration ni de validation maker/checker.',
                'actif' => true,
            ]
        );

        if (Schema::hasTable('role_module')) {
            $exists = DB::table('role_module')
                ->where('role_id', $role->id)
                ->where('module', 'od')
                ->exists();

            if (! $exists) {
                DB::table('role_module')->insert([
                    'role_id' => $role->id,
                    'module' => 'od',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        ModuleAccess::clearModuleRolesCache();
    }

    public function down(): void
    {
        $role = Role::query()->where('slug', 'controleur')->first();
        if ($role === null) {
            return;
        }

        if (Schema::hasTable('role_module')) {
            DB::table('role_module')->where('role_id', $role->id)->delete();
        }

        if (Schema::hasTable('user_role')) {
            DB::table('user_role')->where('role_id', $role->id)->delete();
        }

        $role->delete();
        ModuleAccess::clearModuleRolesCache();
    }
};
