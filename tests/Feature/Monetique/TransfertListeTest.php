<?php

use App\Models\CoficarteTransfer;
use App\Models\Role;
use App\Models\User;

function superAdminMonetique(): User
{
    $role = Role::query()->firstOrCreate(['slug' => 'it'], ['nom' => 'SuperAdmin', 'module' => 'config', 'actif' => true]);
    $user = User::factory()->create();
    $user->roles()->attach($role->id);

    return $user->fresh();
}

function creerTransfert(User $initiateur, string $receveur, string $date): CoficarteTransfer
{
    $t = CoficarteTransfer::query()->create([
        'user_id' => $initiateur->id,
        'receveur' => $receveur,
        'debut_plage' => '1',
        'fin_plage' => '2',
        'commentaire' => 'RAS',
        'status' => CoficarteTransfer::STATUS_EN_ATTENTE,
    ]);
    $t->forceFill(['created_at' => $date, 'updated_at' => $date])->save();

    return $t;
}

test('les filtres d’En attente portent sur tous les transferts, pas seulement la page affichée', function () {
    $admin = superAdminMonetique();
    $initiateur = User::factory()->create();

    creerTransfert($initiateur, 'Abdou FAYE — Agence principale Point E (501)', '2026-09-14 10:32:00');
    foreach (range(1, 16) as $i) {
        creerTransfert($initiateur, "Agent {$i} — Agence {$i}", '2026-09-14 12:'.str_pad((string) $i, 2, '0', STR_PAD_LEFT).':00');
    }

    $this->actingAs($admin)->get('/monetique/transferts/en-attente')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('transfers.total', 17)
            ->has('transfers.data', 15)
            ->where('options.receptionnistes', fn ($r) => collect($r)->contains('Abdou FAYE — Agence principale Point E (501)')));

    $this->actingAs($admin)->get('/monetique/transferts/en-attente?page=2')
        ->assertInertia(fn ($page) => $page->has('transfers.data', 2));

    $this->actingAs($admin)->get('/monetique/transferts/en-attente?q=abdou')
        ->assertInertia(fn ($page) => $page->where('transfers.total', 1));

    $this->actingAs($admin)->get('/monetique/transferts/historique?receptionniste='.urlencode('Abdou FAYE — Agence principale Point E (501)'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('transfers.total', 1));
});
