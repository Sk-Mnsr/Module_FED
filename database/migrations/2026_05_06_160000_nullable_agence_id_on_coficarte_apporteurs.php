<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $this->upSqlite();

            return;
        }

        if (! Schema::hasTable('coficarte_apporteurs')) {
            return;
        }

        $this->dropAgenceForeignKeyIfExists($driver);

        if (! Schema::hasColumn('coficarte_apporteurs', 'agence_id')) {
            Schema::table('coficarte_apporteurs', function (Blueprint $table) {
                $table->foreignId('agence_id')->nullable()->constrained('agences')->nullOnDelete();
            });

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE coficarte_apporteurs ALTER COLUMN agence_id DROP NOT NULL');
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE coficarte_apporteurs MODIFY agence_id BIGINT UNSIGNED NULL');
        }

        Schema::table('coficarte_apporteurs', function (Blueprint $table) {
            $table->foreign('agence_id')->references('id')->on('agences')->nullOnDelete();
        });
    }

    private function dropAgenceForeignKeyIfExists(string $driver): void
    {
        if ($driver === 'pgsql') {
            // DROP IF EXISTS: nom Laravel par défaut ; certaines bases n’ont pas cette FK (import, ancien schéma).
            DB::statement('ALTER TABLE coficarte_apporteurs DROP CONSTRAINT IF EXISTS coficarte_apporteurs_agence_id_foreign');

            $rows = DB::select(
                "SELECT tc.constraint_name
                FROM information_schema.table_constraints tc
                INNER JOIN information_schema.key_column_usage kcu
                    ON tc.constraint_schema = kcu.constraint_schema
                    AND tc.constraint_name = kcu.constraint_name
                WHERE tc.constraint_type = 'FOREIGN KEY'
                    AND tc.table_schema = current_schema()
                    AND tc.table_name = 'coficarte_apporteurs'
                    AND kcu.column_name = 'agence_id'"
            );
            $seen = [];
            foreach ($rows as $row) {
                $name = $row->constraint_name;
                if ($name === '' || isset($seen[$name])) {
                    continue;
                }
                $seen[$name] = true;
                DB::statement('ALTER TABLE coficarte_apporteurs DROP CONSTRAINT IF EXISTS "'.$name.'"');
            }

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
                [$dbName, 'coficarte_apporteurs', 'agence_id']
            );
            foreach ($rows as $row) {
                $name = $row->constraint_name;
                DB::statement('ALTER TABLE coficarte_apporteurs DROP FOREIGN KEY `'.$name.'`');
            }
        }
    }

    private function upSqlite(): void
    {
        Schema::table('coficarte_apporteurs', function (Blueprint $table) {
            if ($this->foreignKeyExistsSqlite('coficarte_apporteurs', 'agence_id')) {
                $table->dropForeign(['agence_id']);
            }
        });

        DB::statement('PRAGMA foreign_keys=OFF');
        DB::statement('CREATE TABLE coficarte_apporteurs__new (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            agence_id INTEGER NULL,
            code VARCHAR(64) NULL,
            nom VARCHAR(191) NOT NULL,
            telephone VARCHAR(64) NULL,
            email VARCHAR(191) NULL,
            actif INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            FOREIGN KEY (agence_id) REFERENCES agences(id) ON DELETE SET NULL
        )');
        DB::statement('INSERT INTO coficarte_apporteurs__new (id, agence_id, code, nom, telephone, email, actif, created_at, updated_at)
            SELECT id, agence_id, code, nom, telephone, email, actif, created_at, updated_at FROM coficarte_apporteurs');
        DB::statement('DROP TABLE coficarte_apporteurs');
        DB::statement('ALTER TABLE coficarte_apporteurs__new RENAME TO coficarte_apporteurs');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS coficarte_apporteurs_code_unique ON coficarte_apporteurs (code)');
        DB::statement('PRAGMA foreign_keys=ON');
    }

    /** @param  'from'|'to'  $column */
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
        // Annulation non fiable si des apporteurs ont agence_id NULL
    }
};
