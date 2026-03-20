<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('feds', function (Blueprint $blueprint) {
            $blueprint->boolean('expert_opinion_requested')->default(false)->after('facilities_validated_by');
            $blueprint->unsignedBigInteger('expert_opinion_offre_id')->nullable()->after('expert_opinion_requested');
            $blueprint->text('expert_opinion_comment')->nullable()->after('expert_opinion_offre_id');
            $blueprint->timestamp('expert_opinion_at')->nullable()->after('expert_opinion_comment');
            
            $blueprint->foreign('expert_opinion_offre_id')->references('id')->on('fed_fournisseur_offres')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feds', function (Blueprint $blueprint) {
            $blueprint->dropForeign(['expert_opinion_offre_id']);
            $blueprint->dropColumn([
                'expert_opinion_requested',
                'expert_opinion_offre_id',
                'expert_opinion_comment',
                'expert_opinion_at',
            ]);
        });
    }
};
