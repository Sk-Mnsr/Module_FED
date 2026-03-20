<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('feds')
            ->whereIn('status', ['draft', 'submitted'])
            ->update(['status' => 'pending_validation']);
    }

    public function down(): void
    {
        // Cannot reliably restore draft vs submitted
    }
};
