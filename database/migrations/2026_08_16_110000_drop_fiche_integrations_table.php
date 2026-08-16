<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('fiche_integrations');
    }

    public function down(): void
    {
        // Table retirée volontairement — ne pas recréer.
    }
};
