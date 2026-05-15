<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coficarte_supply_requests', function (Blueprint $table) {
            $table->unsignedInteger('quantite_livree')->default(0);
            $table->boolean('cloture_partielle')->default(false);
        });

        Schema::table('coficarte_transfers', function (Blueprint $table) {
            $table->string('supply_request_completion', 16)->nullable()->after('supply_request_id');
        });

        Schema::table('coficarte_transfers', function (Blueprint $table) {
            $table->dropUnique(['supply_request_id']);
        });

        Schema::table('coficarte_transfers', function (Blueprint $table) {
            $table->index('supply_request_id');
        });

        $this->backfillSupplyRequests();
    }

    private function backfillSupplyRequests(): void
    {
        $transferIds = DB::table('coficarte_transfers')
            ->whereNotNull('supply_request_id')
            ->distinct()
            ->pluck('supply_request_id');

        foreach ($transferIds as $srId) {
            $srId = (int) $srId;
            $sr = DB::table('coficarte_supply_requests')->where('id', $srId)->first();
            if ($sr === null) {
                continue;
            }

            $transfers = DB::table('coficarte_transfers')
                ->where('supply_request_id', $srId)
                ->orderBy('id')
                ->get();

            $livree = 0;
            $hasPending = false;
            foreach ($transfers as $t) {
                if ($t->status === 'en_attente') {
                    $hasPending = true;
                }
                if ($t->status === 'valide') {
                    $livree += $this->countCardsForTransferRow($t);
                }
            }

            $updates = [
                'quantite_livree' => $livree,
            ];

            if ($sr->status === 'acceptee') {
                if ($hasPending) {
                    $updates['status'] = 'transfert_en_cours';
                } elseif ($livree >= (int) $sr->quantite_demandee) {
                    $updates['status'] = 'acceptee';
                } elseif ($livree > 0) {
                    $updates['status'] = 'partielle';
                } else {
                    $updates['status'] = 'en_attente';
                }
            }

            DB::table('coficarte_supply_requests')->where('id', $srId)->update($updates);
        }
    }

    /**
     * @param  object{card_ids?: string|null, debut_plage?: string|null, fin_plage?: string|null}  $t
     */
    private function countCardsForTransferRow(object $t): int
    {
        $ids = json_decode($t->card_ids ?? 'null', true);
        if (is_array($ids) && count($ids) > 0) {
            return count($ids);
        }

        $debut = $t->debut_plage ?? null;
        $fin = $t->fin_plage ?? null;
        if (is_string($debut) && $debut !== '' && is_string($fin) && $fin !== '') {
            return (int) DB::table('coficarte_cards')
                ->whereBetween('numero_carte', [$debut, $fin])
                ->count();
        }

        return 0;
    }

    public function down(): void
    {
        Schema::table('coficarte_transfers', function (Blueprint $table) {
            $table->dropIndex(['supply_request_id']);
        });

        Schema::table('coficarte_transfers', function (Blueprint $table) {
            $table->unique('supply_request_id');
        });

        Schema::table('coficarte_transfers', function (Blueprint $table) {
            $table->dropColumn('supply_request_completion');
        });

        Schema::table('coficarte_supply_requests', function (Blueprint $table) {
            $table->dropColumn(['quantite_livree', 'cloture_partielle']);
        });
    }
};
