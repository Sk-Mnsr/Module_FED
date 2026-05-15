<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_profile_check');

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_profile_check CHECK (profile::text = ANY (ARRAY['admin'::character varying, 'other'::character varying, 'monetique'::character varying]))");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::table('users')->where('profile', 'monetique')->update(['profile' => 'other']);

        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_profile_check');

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_profile_check CHECK (profile::text = ANY (ARRAY['admin'::character varying, 'other'::character varying]))");
    }
};
