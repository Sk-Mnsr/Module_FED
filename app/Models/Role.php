<?php

namespace App\Models;

use App\Support\ModuleAccess;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maravel\Models\Role as BaseRole;

/**
 * Rôle applicatif : colonnes métier historiques (slug, module…)
 * + colonnes RBAC Maravel (name, label, is_super_admin).
 */
class Role extends BaseRole
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'slug',
        'name',
        'label',
        'module',
        'access_profile',
        'description',
        'actif',
        'is_super_admin',
    ];

    protected $casts = [
        'actif' => 'boolean',
        'is_super_admin' => 'boolean',
    ];

    /**
     * @return list<string>
     */
    public function moduleKeys(): array
    {
        if (! Schema::hasTable('role_module')) {
            return filled($this->module) ? [$this->module] : [];
        }

        return DB::table('role_module')
            ->where('role_id', $this->id)
            ->orderBy('module')
            ->pluck('module')
            ->all();
    }

    /**
     * @param  list<string>  $modules
     */
    public function syncModuleKeys(array $modules): void
    {
        if (! Schema::hasTable('role_module')) {
            return;
        }

        $modules = array_values(array_unique(array_filter($modules)));

        DB::table('role_module')->where('role_id', $this->id)->delete();

        $now = now();
        foreach ($modules as $module) {
            DB::table('role_module')->insert([
                'role_id' => $this->id,
                'module' => $module,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        ModuleAccess::clearModuleRolesCache();
    }
}
