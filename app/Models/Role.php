<?php

namespace App\Models;

use App\Support\ModuleAccess;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'slug',
        'module',
        'access_profile',
        'description',
        'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
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

    /**
     * Relation avec les utilisateurs (many-to-many)
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_role', 'role_id', 'user_id');
    }
}
