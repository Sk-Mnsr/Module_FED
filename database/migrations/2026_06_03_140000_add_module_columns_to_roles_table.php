<?php

use App\Support\ModuleAccess;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('module', 32)->nullable()->after('slug');
            $table->string('access_profile', 16)->nullable()->after('module');
        });

        foreach (DB::table('roles')->get(['id', 'slug']) as $role) {
            $slug = ModuleAccess::normalizeRoleSlug($role->slug);

            DB::table('roles')->where('id', $role->id)->update([
                'module' => ModuleAccess::primaryModuleForRoleSlug($slug),
                'access_profile' => ModuleAccess::accessProfileForRoleSlug($slug),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['module', 'access_profile']);
        });
    }
};
