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
            $table->unsignedBigInteger('n_plus_1_user_id')->nullable()->after('department_id');
            $table->unsignedBigInteger('n_plus_2_user_id')->nullable()->after('n_plus_1_user_id');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->unsignedBigInteger('manager_user_id')->nullable()->after('code');
        });

        $this->migrateHierarchyData();

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('n_plus_1_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('n_plus_2_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('manager_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['manager_profile_id']);
            $table->dropColumn('manager_profile_id');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('manager_profile_id')->nullable()->after('code')->constrained('profiles')->nullOnDelete();
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['manager_user_id']);
            $table->dropColumn('manager_user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['n_plus_1_user_id']);
            $table->dropForeign(['n_plus_2_user_id']);
            $table->dropColumn(['n_plus_1_user_id', 'n_plus_2_user_id']);
        });
    }

    private function migrateHierarchyData(): void
    {
        if (! Schema::hasTable('profiles')) {
            return;
        }

        $profileToUser = [];

        foreach (DB::table('profiles')->whereNotNull('email')->get() as $profile) {
            $user = DB::table('users')
                ->whereRaw('lower(trim(email)) = lower(trim(?))', [$profile->email])
                ->first();

            if ($user !== null) {
                $profileToUser[(int) $profile->id] = (int) $user->id;
            }
        }

        foreach (DB::table('profiles')->get() as $profile) {
            $userId = $profileToUser[(int) $profile->id] ?? null;
            if ($userId === null) {
                continue;
            }

            $updates = [];

            if ($profile->n_plus_1_id !== null) {
                $n1UserId = $profileToUser[(int) $profile->n_plus_1_id] ?? null;
                if ($n1UserId !== null) {
                    $updates['n_plus_1_user_id'] = $n1UserId;
                }
            }

            if ($profile->n_plus_2_id !== null) {
                $n2UserId = $profileToUser[(int) $profile->n_plus_2_id] ?? null;
                if ($n2UserId !== null) {
                    $updates['n_plus_2_user_id'] = $n2UserId;
                }
            }

            if ($updates !== []) {
                DB::table('users')->where('id', $userId)->update($updates);
            }
        }

        foreach (DB::table('departments')->whereNotNull('manager_profile_id')->get() as $department) {
            $managerUserId = $profileToUser[(int) $department->manager_profile_id] ?? null;
            if ($managerUserId === null) {
                continue;
            }

            DB::table('departments')->where('id', $department->id)->update([
                'manager_user_id' => $managerUserId,
            ]);
        }
    }
};
