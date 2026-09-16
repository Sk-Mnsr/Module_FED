<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pod_operations', function (Blueprint $table) {
            $table->foreignId('validated_by_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable()->after('statut');
            $table->string('motif_annulation')->nullable()->after('validated_at');
        });
    }

    public function down(): void
    {
        Schema::table('pod_operations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('validated_by_user_id');
            $table->dropColumn(['validated_at', 'motif_annulation']);
        });
    }
};
