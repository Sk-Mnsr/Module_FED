<?php

use App\Models\User;
use App\Support\ModuleAccess;
use App\Support\RoleAccessProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aligne users.profile sur le modèle Module + Rôle :
 * - monetique → other (accès Monétique via rôles / role_module)
 * - admin uniquement si rôle it/admin
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        DB::table('users')->where('profile', 'monetique')->update(['profile' => 'other']);

        if (! Schema::hasTable('roles') || ! Schema::hasTable('user_role')) {
            ModuleAccess::clearModuleRolesCache();

            return;
        }

        User::query()->with('roles')->orderBy('id')->chunkById(100, function ($users) {
            foreach ($users as $user) {
                $expected = RoleAccessProfile::forRoles($user->roles);
                if ($user->profile !== $expected) {
                    $user->forceFill(['profile' => $expected])->saveQuietly();
                }
            }
        });

        ModuleAccess::clearModuleRolesCache();
    }

    public function down(): void
    {
        // Irréversible volontairement : on ne restaure pas profile=monetique.
    }
};
