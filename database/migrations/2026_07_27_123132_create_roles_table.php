<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * La table `roles` existe déjà (création métier du 23/02). On y ajoute
     * uniquement les colonnes attendues par le RBAC Maravel.
     */
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('label')->nullable();
                $table->string('description')->nullable();
                $table->boolean('is_super_admin')->default(false);
                $table->timestamps();
            });

            return;
        }

        Schema::table('roles', function (Blueprint $table) {
            if (! Schema::hasColumn('roles', 'name')) {
                $table->string('name')->nullable()->after('id');
            }
            if (! Schema::hasColumn('roles', 'label')) {
                $table->string('label')->nullable();
            }
            if (! Schema::hasColumn('roles', 'is_super_admin')) {
                $table->boolean('is_super_admin')->default(false);
            }
        });

        // Aligner name / label sur les colonnes historiques (slug, nom).
        if (Schema::hasColumn('roles', 'slug')) {
            DB::table('roles')
                ->where(function ($q) {
                    $q->whereNull('name')->orWhere('name', '');
                })
                ->update([
                    'name' => DB::raw('slug'),
                ]);
        }

        if (Schema::hasColumn('roles', 'nom') && Schema::hasColumn('roles', 'label')) {
            DB::table('roles')
                ->whereNull('label')
                ->update([
                    'label' => DB::raw('nom'),
                ]);
        }

        if (Schema::hasColumn('roles', 'slug')) {
            DB::table('roles')
                ->whereIn('slug', ['it', 'admin'])
                ->update(['is_super_admin' => true]);
        }

        // Unique sur name si possible (ignore si déjà présent / doublons).
        if (Schema::hasColumn('roles', 'name')) {
            $indexes = Schema::getIndexes('roles');
            $hasUniqueName = collect($indexes)->contains(function (array $index) {
                return ($index['unique'] ?? false)
                    && ($index['columns'] ?? []) === ['name'];
            });

            $duplicates = DB::table('roles')
                ->select('name', DB::raw('count(*) as c'))
                ->whereNotNull('name')
                ->groupBy('name')
                ->havingRaw('count(*) > 1')
                ->exists();

            if (! $hasUniqueName && ! $duplicates) {
                Schema::table('roles', function (Blueprint $table) {
                    $table->unique('name');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        // Ne pas dropper la table historique : retirer seulement les colonnes Maravel.
        Schema::table('roles', function (Blueprint $table) {
            if (Schema::hasColumn('roles', 'is_super_admin')) {
                $table->dropColumn('is_super_admin');
            }
            if (Schema::hasColumn('roles', 'label') && ! Schema::hasColumn('roles', 'nom')) {
                $table->dropColumn('label');
            }
            if (Schema::hasColumn('roles', 'name') && ! Schema::hasColumn('roles', 'slug')) {
                $table->dropColumn('name');
            }
        });
    }
};
