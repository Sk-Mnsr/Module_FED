<?php

use App\Models\Agence;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('agences')
            ->where('code', Agence::CODE_SIEGE)
            ->update(['chef_agence_user_id' => null]);
    }

    public function down(): void
    {
        //
    }
};
