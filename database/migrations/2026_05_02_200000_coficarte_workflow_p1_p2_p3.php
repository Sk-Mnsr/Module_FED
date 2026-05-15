<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coficarte_supply_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->foreignId('chef_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('quantite_demandee');
            $table->text('commentaire')->nullable();
            $table->string('status', 32)->default('en_attente');
            $table->text('reponse_monetique')->nullable();
            $table->foreignId('traite_par_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('traite_le')->nullable();
            $table->timestamps();
        });

        Schema::table('coficarte_transfers', function (Blueprint $table) {
            $table->foreignId('supply_request_id')->nullable()->unique()->after('user_id')->constrained('coficarte_supply_requests')->nullOnDelete();
            $table->string('bon_numero', 32)->nullable()->unique()->after('fin_plage');
        });

        Schema::create('coficarte_card_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coficarte_card_id')->constrained('coficarte_cards')->cascadeOnDelete();
            $table->string('event_type', 64);
            $table->json('meta')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('coficarte_apporteurs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('nom', 191);
            $table->string('telephone', 64)->nullable();
            $table->string('email', 191)->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('coficarte_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 191);
            $table->text('description')->nullable();
            $table->foreignId('agence_id')->nullable()->constrained('agences')->cascadeOnDelete();
            $table->unsignedInteger('objectif_ventes')->default(0);
            $table->unsignedBigInteger('objectif_montant_recharges')->default(0);
            $table->date('date_debut');
            $table->date('date_fin');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('coficarte_sales', function (Blueprint $table) {
            $table->string('payment_status', 32)->default('en_attente')->after('locked');
            $table->foreignId('coficarte_apporteur_id')->nullable()->after('payment_status')->constrained('coficarte_apporteurs')->nullOnDelete();
            $table->foreignId('coficarte_campaign_id')->nullable()->after('coficarte_apporteur_id')->constrained('coficarte_campaigns')->nullOnDelete();
            $table->string('kyc_type_piece', 64)->nullable()->after('type_compte');
            $table->string('kyc_numero_piece', 128)->nullable()->after('kyc_type_piece');
            $table->date('kyc_date_emission')->nullable()->after('kyc_numero_piece');
            $table->timestamp('activated_at')->nullable()->after('kyc_date_emission');
        });

        DB::table('coficarte_sales')->update([
            'payment_status' => 'encaisse',
            'activated_at' => DB::raw('COALESCE(updated_at, created_at)'),
        ]);

        Schema::create('coficarte_recharges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coficarte_card_id')->constrained('coficarte_cards')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('montant');
            $table->string('payment_status', 32)->default('en_attente');
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->text('commentaire')->nullable();
            $table->foreignId('coficarte_campaign_id')->nullable()->constrained('coficarte_campaigns')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('coficarte_stock_thresholds', function (Blueprint $table) {
            $table->id();
            $table->string('cible', 16);
            $table->foreignId('agence_id')->nullable()->constrained('agences')->cascadeOnDelete();
            $table->unsignedInteger('min_cards')->default(0);
            $table->timestamps();
            $table->unique(['cible', 'agence_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coficarte_stock_thresholds');
        Schema::dropIfExists('coficarte_recharges');

        Schema::table('coficarte_sales', function (Blueprint $table) {
            $table->dropForeign(['coficarte_apporteur_id']);
            $table->dropForeign(['coficarte_campaign_id']);
            $table->dropColumn([
                'payment_status',
                'coficarte_apporteur_id',
                'coficarte_campaign_id',
                'kyc_type_piece',
                'kyc_numero_piece',
                'kyc_date_emission',
                'activated_at',
            ]);
        });

        Schema::dropIfExists('coficarte_campaigns');
        Schema::dropIfExists('coficarte_apporteurs');

        Schema::dropIfExists('coficarte_card_movements');

        Schema::table('coficarte_transfers', function (Blueprint $table) {
            $table->dropForeign(['supply_request_id']);
            $table->dropColumn(['supply_request_id', 'bon_numero']);
        });

        Schema::dropIfExists('coficarte_supply_requests');
    }
};
