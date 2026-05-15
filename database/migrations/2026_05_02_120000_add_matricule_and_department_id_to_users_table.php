<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('matricule', 128)->nullable()->after('agence_id');
            $table->foreignId('department_id')->nullable()->after('matricule')->constrained('departments')->nullOnDelete();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('
                UPDATE users u
                SET matricule = p.matricule,
                    department_id = p.department_id
                FROM profiles p
                WHERE u.email IS NOT NULL
                  AND p.email IS NOT NULL
                  AND lower(trim(u.email::text)) = lower(trim(p.email::text))
            ');
        } else {
            foreach (DB::table('users')->orderBy('id')->cursor() as $u) {
                if ($u->email === null) {
                    continue;
                }
                $p = DB::table('profiles')->whereRaw('lower(trim(email)) = lower(trim(?))', [$u->email])->first();
                if ($p) {
                    DB::table('users')->where('id', $u->id)->update([
                        'matricule' => $p->matricule,
                        'department_id' => $p->department_id,
                    ]);
                }
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('matricule');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['matricule']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn(['matricule', 'department_id']);
        });
    }
};
