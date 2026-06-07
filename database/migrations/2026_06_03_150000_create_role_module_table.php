<?php

use App\Support\ModuleAccess;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, list<string>> */
    private const LEGACY_MODULE_ROLES = [
        'fed' => [
            'it', 'demandeur', 'n_plus_1', 'responsable_achats', 'responsable_facilities',
            'responsable_stock', 'controle_de_gestion', 'daf', 'dga', 'assistant_comptable',
        ],
        'budget' => ['it', 'n_plus_1', 'controle_de_gestion', 'daf', 'demandeur'],
        'stock' => ['it', 'responsable_achats', 'responsable_stock'],
        'ecritures' => ['it', 'controle_de_gestion', 'daf'],
        'monetique' => ['it', 'monetique', 'monetique_ops', 'ca', 'cc', 'caissier'],
        'od' => ['it', 'ops', 'finance', 'controle_de_gestion', 'daf'],
        'config' => ['it', 'admin'],
    ];

    public function up(): void
    {
        Schema::create('role_module', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('module', 32);
            $table->timestamps();
            $table->unique(['role_id', 'module']);
        });

        $now = now();

        foreach (self::LEGACY_MODULE_ROLES as $module => $slugs) {
            foreach ($slugs as $slug) {
                $roleId = DB::table('roles')->where('slug', $slug)->value('id');
                if ($roleId === null) {
                    continue;
                }

                DB::table('role_module')->updateOrInsert(
                    ['role_id' => $roleId, 'module' => $module],
                    ['created_at' => $now, 'updated_at' => $now],
                );
            }
        }

        // Rôles avec module principal mais absents de la matrice legacy
        foreach (DB::table('roles')->whereNotNull('module')->get(['id', 'module']) as $role) {
            DB::table('role_module')->updateOrInsert(
                ['role_id' => $role->id, 'module' => $role->module],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }

        ModuleAccess::clearModuleRolesCache();
    }

    public function down(): void
    {
        Schema::dropIfExists('role_module');
        ModuleAccess::clearModuleRolesCache();
    }
};
