<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coficarte_recharges')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $this->upSqlite();

            return;
        }

        Schema::table('coficarte_recharges', function (Blueprint $table) {
            $table->string('numero_carte_saisi', 64)->nullable()->after('coficarte_campaign_id');
            $table->foreignId('agence_enregistrement_id')->nullable()->after('numero_carte_saisi')->constrained('agences')->nullOnDelete();
        });

        $this->backfillNonSqlite($driver);

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::table('coficarte_recharges')->where(function ($q) {
                $q->whereNull('numero_carte_saisi')->orWhere('numero_carte_saisi', '');
            })->update(['numero_carte_saisi' => DB::raw('CONCAT("#", id)')]);
        }

        if ($driver === 'pgsql') {
            DB::statement(
                "UPDATE coficarte_recharges SET numero_carte_saisi = '#' || id::text WHERE numero_carte_saisi IS NULL OR TRIM(numero_carte_saisi) = ''"
            );
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE coficarte_recharges ALTER COLUMN numero_carte_saisi SET NOT NULL');
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE coficarte_recharges MODIFY numero_carte_saisi VARCHAR(64) NOT NULL');
        }

        $this->dropCoficarteCardForeignKeyIfExists($driver);

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE coficarte_recharges ALTER COLUMN coficarte_card_id DROP NOT NULL');
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE coficarte_recharges MODIFY coficarte_card_id BIGINT UNSIGNED NULL');
        }

        Schema::table('coficarte_recharges', function (Blueprint $table) {
            $table->foreign('coficarte_card_id')->references('id')->on('coficarte_cards')->nullOnDelete();
        });
    }

    private function backfillNonSqlite(string $driver): void
    {
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement(
                'UPDATE coficarte_recharges r
                INNER JOIN coficarte_cards c ON c.id = r.coficarte_card_id
                LEFT JOIN users u ON u.id = r.user_id
                SET r.numero_carte_saisi = c.numero_carte,
                    r.agence_enregistrement_id = COALESCE(c.agence_id, u.agence_id)'
            );

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement(
                'UPDATE coficarte_recharges r
                SET
                    numero_carte_saisi = (SELECT c.numero_carte FROM coficarte_cards c WHERE c.id = r.coficarte_card_id LIMIT 1),
                    agence_enregistrement_id = COALESCE(
                        (SELECT c.agence_id FROM coficarte_cards c WHERE c.id = r.coficarte_card_id LIMIT 1),
                        (SELECT u.agence_id FROM users u WHERE u.id = r.user_id LIMIT 1)
                    )'
            );
        }
    }

    private function dropCoficarteCardForeignKeyIfExists(string $driver): void
    {
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE coficarte_recharges DROP CONSTRAINT IF EXISTS coficarte_recharges_coficarte_card_id_foreign');

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $dbName = DB::getDatabaseName();
            $rows = DB::select(
                'SELECT CONSTRAINT_NAME AS constraint_name
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = ?
                    AND TABLE_NAME = ?
                    AND COLUMN_NAME = ?
                    AND REFERENCED_TABLE_NAME IS NOT NULL',
                [$dbName, 'coficarte_recharges', 'coficarte_card_id']
            );
            foreach ($rows as $row) {
                $name = $row->constraint_name;
                DB::statement('ALTER TABLE coficarte_recharges DROP FOREIGN KEY `'.$name.'`');
            }
        }
    }

    private function upSqlite(): void
    {
        Schema::table('coficarte_recharges', function (Blueprint $table) {
            if ($this->foreignKeyExistsSqlite('coficarte_recharges', 'coficarte_card_id')) {
                $table->dropForeign(['coficarte_card_id']);
            }
            if ($this->foreignKeyExistsSqlite('coficarte_recharges', 'user_id')) {
                $table->dropForeign(['user_id']);
            }
            if ($this->foreignKeyExistsSqlite('coficarte_recharges', 'confirmed_by_user_id')) {
                $table->dropForeign(['confirmed_by_user_id']);
            }
            if ($this->foreignKeyExistsSqlite('coficarte_recharges', 'coficarte_campaign_id')) {
                $table->dropForeign(['coficarte_campaign_id']);
            }
        });

        DB::statement('PRAGMA foreign_keys=OFF');
        DB::statement('CREATE TABLE coficarte_recharges__new (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            coficarte_card_id INTEGER NULL,
            user_id INTEGER NOT NULL,
            montant INTEGER NOT NULL,
            titulaire_carte VARCHAR(255) NULL,
            email_titulaire VARCHAR(191) NULL,
            honoraire_chargement INTEGER NOT NULL DEFAULT 0,
            payment_status VARCHAR(32) NOT NULL DEFAULT \'en_attente\',
            encaissement_code VARCHAR(32) NULL,
            bordereau_caisse_path VARCHAR(255) NULL,
            confirmed_by_user_id INTEGER NULL,
            confirmed_at DATETIME NULL,
            commentaire TEXT NULL,
            coficarte_campaign_id INTEGER NULL,
            numero_carte_saisi VARCHAR(64) NOT NULL,
            agence_enregistrement_id INTEGER NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            FOREIGN KEY (coficarte_card_id) REFERENCES coficarte_cards(id) ON DELETE SET NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (confirmed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (coficarte_campaign_id) REFERENCES coficarte_campaigns(id) ON DELETE SET NULL,
            FOREIGN KEY (agence_enregistrement_id) REFERENCES agences(id) ON DELETE SET NULL
        )');

        DB::statement(
            'INSERT INTO coficarte_recharges__new (
                id, coficarte_card_id, user_id, montant, titulaire_carte, email_titulaire, honoraire_chargement,
                payment_status, encaissement_code, bordereau_caisse_path, confirmed_by_user_id, confirmed_at,
                commentaire, coficarte_campaign_id, numero_carte_saisi, agence_enregistrement_id, created_at, updated_at
            )
            SELECT
                r.id, r.coficarte_card_id, r.user_id, r.montant, r.titulaire_carte, r.email_titulaire, r.honoraire_chargement,
                r.payment_status, r.encaissement_code, r.bordereau_caisse_path, r.confirmed_by_user_id, r.confirmed_at,
                r.commentaire, r.coficarte_campaign_id,
                COALESCE(NULLIF(TRIM(c.numero_carte), ""), "#" || r.id),
                COALESCE(c.agence_id, u.agence_id),
                r.created_at, r.updated_at
            FROM coficarte_recharges r
            LEFT JOIN coficarte_cards c ON c.id = r.coficarte_card_id
            LEFT JOIN users u ON u.id = r.user_id'
        );

        DB::statement('DROP TABLE coficarte_recharges');
        DB::statement('ALTER TABLE coficarte_recharges__new RENAME TO coficarte_recharges');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS coficarte_recharges_encaissement_code_unique ON coficarte_recharges (encaissement_code)');
        DB::statement('PRAGMA foreign_keys=ON');
    }

    private function foreignKeyExistsSqlite(string $table, string $column): bool
    {
        $rows = DB::select('PRAGMA foreign_key_list('.$table.')');
        foreach ($rows as $row) {
            if (isset($row->from) && $row->from === $column) {
                return true;
            }
        }

        return false;
    }

    public function down(): void
    {
        // Irréversible proprement si des lignes ont coficarte_card_id NULL
    }
};
