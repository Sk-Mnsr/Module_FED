<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coficarte_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('numero_carte', 64)->unique();
            $table->string('reference_facture', 128);
            $table->string('facture_path')->nullable();
            $table->unsignedInteger('prix_vente');
            $table->date('date_livraison');
            $table->date('date_expiration');
            $table->string('status', 32)->default('en_stock');
            $table->string('possesseur', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('coficarte_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('receveur', 255);
            $table->string('debut_plage', 64);
            $table->string('fin_plage', 64);
            $table->text('commentaire')->nullable();
            $table->string('status', 32)->default('en_attente');
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('coficarte_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('coficarte_card_id')->constrained('coficarte_cards')->restrictOnDelete();
            $table->date('date_vente');
            $table->string('derniers_4', 4);
            $table->string('type_acheteur', 64);
            $table->string('nom_client', 255);
            $table->string('numero_compte_client', 64);
            $table->string('telephone_client', 64);
            $table->string('type_compte', 64);
            $table->boolean('locked')->default(true);
            $table->timestamps();

            $table->unique('coficarte_card_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coficarte_sales');
        Schema::dropIfExists('coficarte_transfers');
        Schema::dropIfExists('coficarte_cards');
    }
};
