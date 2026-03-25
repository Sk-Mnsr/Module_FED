<?php

namespace App\Http\Controllers;

use App\Models\AppelOffre;
use App\Models\Comite;
use App\Models\User;
use App\Notifications\CleOuvertureNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ComiteController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);
        $user = auth()->user();
        
        $query = Comite::with(['appelOffre', 'membres'])
            ->orderByDesc('created_at');

        // Si l'utilisateur n'est pas Responsable Achats (et pas SuperAdmin/IT), 
        // on ne lui affiche que les comités où il est membre.
        if (!$user->hasRole('responsable_achats') && !$user->hasRole('it') && !$user->isSuperAdmin()) {
            $query->whereHas('membres', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        $comites = $query->paginate($perPage);

        return Inertia::render('Comites/Index', [
            'comites' => $comites,
        ]);
    }

    public function create(AppelOffre $appelOffre)
    {
        $users = User::all(['id', 'name', 'email']);
        return Inertia::render('Comites/Manage', [
            'appelOffre' => $appelOffre,
            'comite' => null,
            'users' => $users,
        ]);
    }

    public function edit(Comite $comite)
    {
        $comite->load('membres', 'appelOffre');
        $users = User::all(['id', 'name', 'email']);
        return Inertia::render('Comites/Manage', [
            'appelOffre' => $comite->appelOffre,
            'comite' => $comite,
            'users' => $users,
        ]);
    }

    public function store(Request $request, AppelOffre $appelOffre)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'membres' => 'required|array',
            'membres.*.user_id' => 'required|exists:users,id',
            'membres.*.role' => 'required|in:president,membre,secretaire',
        ]);

        DB::beginTransaction();

        $comite = $appelOffre->comite()->create([
            'nom' => $validated['nom'],
        ]);

        // Génération de la clé partagée si l'appel d'offre n'en a pas
        $masterKey = strtoupper(Str::random(count($validated['membres']) * 2));
        $appelOffre->update(['cle_ouverture_hash' => Hash::make($masterKey)]);

        $parts = str_split($masterKey, 2);
        $membresWithRoles = [];
        
        foreach ($validated['membres'] as $index => $membre) {
            $membresWithRoles[$membre['user_id']] = ['role' => $membre['role']];
            
            // Notification du membre
            $user = User::find($membre['user_id']);
            if ($user && isset($parts[$index])) {
                $user->notify(new CleOuvertureNotification($appelOffre, $parts[$index], $index + 1));
            }
        }

        $comite->membres()->sync($membresWithRoles);

        DB::commit();

        return redirect()->route('appel-offres.show', $appelOffre)->with('success', 'Comité créé et membres assignés.');
    }

    public function update(Request $request, Comite $comite)
    {
        $validated = $request->validate([
            'membres' => 'required|array',
            'membres.*.user_id' => 'required|exists:users,id',
            'membres.*.role' => 'required|in:president,membre,secretaire',
        ]);

        // Si on met à jour, il faudrait idéalement regénérer la clé si les membres changent,
        // mais pour l'instant, disons qu'on génère une nouvelle clé partagée complète (ou on garde l'ancienne).
        // On va générer une nouvelle clé pour invalider l'ancienne.
        $masterKey = strtoupper(Str::random(count($validated['membres']) * 2));
        $comite->appelOffre->update(['cle_ouverture_hash' => Hash::make($masterKey), 'is_plis_ouverts' => false]);
        $parts = str_split($masterKey, 2);

        $membresWithRoles = [];
        foreach ($validated['membres'] as $index => $membre) {
            $membresWithRoles[$membre['user_id']] = ['role' => $membre['role']];
            
            $user = User::find($membre['user_id']);
            if ($user && isset($parts[$index])) {
                $user->notify(new CleOuvertureNotification($comite->appelOffre, $parts[$index], $index + 1));
            }
        }

        $comite->membres()->sync($membresWithRoles);

        return redirect()->route('appel-offres.show', $comite->appel_offre_id)->with('success', 'Membres du comité mis à jour.');
    }
}
