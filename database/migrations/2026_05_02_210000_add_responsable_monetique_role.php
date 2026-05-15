<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->updateOrInsert(
            ['slug' => 'responsable_monetique'],
            [
                'nom' => 'Responsable Monétique',
                'description' => 'Gestion centrale Coficarte : création de cartes, modification des prix de vente.',
                'actif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $responsableId = DB::table('roles')->where('slug', 'responsable_monetique')->value('id');
        $monetiqueId = DB::table('roles')->where('slug', 'monetique')->value('id');

        if ($responsableId === null || $monetiqueId === null) {
            return;
        }

        $userIds = DB::table('user_role')->where('role_id', $monetiqueId)->pluck('user_id');
        $now = now();

        foreach ($userIds as $userId) {
            DB::table('user_role')->updateOrInsert(
                ['user_id' => $userId, 'role_id' => $responsableId],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    public function down(): void
    {
        $id = DB::table('roles')->where('slug', 'responsable_monetique')->value('id');
        if ($id !== null) {
            DB::table('user_role')->where('role_id', $id)->delete();
            DB::table('roles')->where('id', $id)->delete();
        }
    }
};
