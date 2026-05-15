<?php

namespace App\Http\Controllers;

use App\Models\Agence;
use App\Models\Department;
use App\Models\Profil;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 5);

        $query = User::with(['profil', 'roles', 'agence', 'department']);

        // Filtre par recherche (nom, email, et autres champs pertinents)
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('matricule', 'like', "%{$search}%")
                    ->orWhereHas('agence', function ($subQ) use ($search) {
                        $subQ->where('nom', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    })
                    ->orWhereHas('profil', function ($subQ) use ($search) {
                        $subQ->where('nom', 'like', "%{$search}%")
                            ->orWhere('prenom', 'like', "%{$search}%")
                            ->orWhere('matricule', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('telephone', 'like', "%{$search}%")
                            ->orWhere('site', 'like', "%{$search}%");
                    });
            });
        }

        // Filtre par activation
        if ($request->has('activation') && $request->activation !== '') {
            $query->where('activated', (bool) $request->activation);
        }

        // Filtre par rôle
        if ($request->has('role') && $request->role) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('roles.id', $request->role);
            });
        }

        $users = $query->orderBy('name')->paginate($perPage);

        // Récupérer les données pour les filtres
        $roles = Role::where('actif', true)->orderBy('nom')->get(['id', 'nom']);

        return Inertia::render('users/Index', [
            'users' => $users,
            'roles' => $roles,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $roles = Role::where('actif', true)->orderBy('nom')->get();
        $departments = Department::orderBy('name')->get(['id', 'name']);
        $agences = Agence::orderBy('nom')->get(['id', 'code', 'nom']);

        return Inertia::render('users/Create', [
            'roles' => $roles,
            'departments' => $departments,
            'agences' => $agences,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (! $request->filled('matricule')) {
            $request->merge(['matricule' => null]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'fonction' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role_id' => 'required|integer|exists:roles,id',
            'department_id' => 'nullable|integer|exists:departments,id',
            'agence_id' => 'nullable|integer|exists:agences,id',
            'matricule' => [
                'nullable',
                'string',
                'max:128',
                Rule::unique('users', 'matricule'),
                Rule::unique('profiles', 'matricule'),
            ],
        ]);

        $rawMatricule = $validated['matricule'] ?? null;
        $matricule = $rawMatricule !== null ? trim((string) $rawMatricule) : '';
        $matricule = $matricule === '' ? null : $matricule;

        $user = User::create([
            'name' => $validated['name'],
            'fonction' => $validated['fonction'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'agence_id' => $validated['agence_id'] ?? null,
            'matricule' => $matricule,
            'department_id' => $validated['department_id'] ?? null,
            'password_change_required' => true,
        ]);

        // Attacher le rôle unique
        $user->roles()->sync([$validated['role_id']]);
        $this->syncUserProfileType($user, $validated['role_id']);
        $user->save();

        $this->syncUserProfil($user);

        return redirect()->route('users.index')
            ->with('success', 'Utilisateur créé avec succès !');
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        $user->load(['profil', 'roles', 'agence', 'department']);

        return Inertia::render('users/Show', [
            'user' => $user,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        $roles = Role::where('actif', true)->orderBy('nom')->get();
        $departments = Department::orderBy('name')->get(['id', 'name']);
        $user->load(['roles', 'profil', 'agence', 'department']);
        $agences = Agence::orderBy('nom')->get(['id', 'code', 'nom']);

        return Inertia::render('users/Edit', [
            'user' => $user,
            'roles' => $roles,
            'departments' => $departments,
            'agences' => $agences,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $user->load('profil');

        if (! $request->filled('matricule')) {
            $request->merge(['matricule' => null]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'fonction' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'role_id' => 'required|integer|exists:roles,id',
            'department_id' => 'nullable|integer|exists:departments,id',
            'agence_id' => 'nullable|integer|exists:agences,id',
            'matricule' => [
                'nullable',
                'string',
                'max:128',
                Rule::unique('users', 'matricule')->ignore($user->id),
                Rule::unique('profiles', 'matricule')->ignore($user->profil?->id),
            ],
        ]);

        $rawMatricule = $validated['matricule'] ?? null;
        $matricule = $rawMatricule !== null ? trim((string) $rawMatricule) : '';
        $matricule = $matricule === '' ? null : $matricule;

        $data = [
            'name' => $validated['name'],
            'fonction' => $validated['fonction'],
            'email' => $validated['email'],
            'agence_id' => $validated['agence_id'] ?? null,
            'matricule' => $matricule,
            'department_id' => $validated['department_id'] ?? null,
        ];

        // Mettre à jour le mot de passe seulement s'il est fourni
        if (! empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
            $data['password_change_required'] = true;
        }

        $user->update($data);

        // Synchroniser le rôle unique
        $user->roles()->sync([$validated['role_id']]);
        $this->syncUserProfileType($user, $validated['role_id']);
        $user->save();

        $this->syncUserProfil($user);

        return redirect()->route('users.index')
            ->with('success', 'Utilisateur mis à jour avec succès !');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'Utilisateur supprimé avec succès !');
    }

    /**
     * Toggle the active status of a user.
     */
    public function toggle(User $user)
    {
        $user->activated = ! $user->activated;
        $user->save();

        Profil::where('email', $user->email)->update(['statut' => $user->activated ? 'actif' : 'inactif']);

        $status = $user->activated ? 'activé' : 'désactivé';

        return redirect()->route('users.index')
            ->with('success', "Utilisateur {$status} avec succès !");
    }

    /**
     * Copie matricule / département vers l’annuaire profiles (N+1, budgets, FED…) pour compatibilité.
     */
    private function syncUserProfil(User $user): void
    {
        $user->refresh();

        $profile = Profil::firstOrNew(['email' => $user->email]);

        if (! $profile->prenom || ! $profile->nom) {
            $parts = preg_split('/\s+/', trim($user->name));
            $profile->prenom = $profile->prenom ?: ($parts[0] ?? null);
            $profile->nom = $profile->nom ?: (count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : null);
        }

        $profile->fonction = $user->fonction;
        $profile->email = $user->email;

        if ($user->department_id) {
            $department = Department::find($user->department_id);
            if ($department) {
                $profile->departement = $department->name;
                $profile->department_id = $department->id;
            }
        } else {
            $profile->departement = null;
            $profile->department_id = null;
        }

        $profile->matricule = $user->matricule;
        $profile->statut = $user->activated ? 'actif' : 'inactif';

        $profile->save();
    }

    private function syncUserProfileType(User $user, int $roleId): void
    {
        $role = Role::find($roleId);
        $adminSlugs = ['it', 'admin'];

        if ($role && in_array($role->slug, $adminSlugs, true)) {
            $user->profile = 'admin';

            return;
        }

        if ($role && in_array($role->slug, ['monetique', 'chef_agence_ca', 'charge_clientele_cc', 'caissier'], true)) {
            $user->profile = 'monetique';

            return;
        }

        $user->profile = 'other';
    }
}
