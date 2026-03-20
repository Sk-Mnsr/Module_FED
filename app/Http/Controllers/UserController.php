<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Department;
use App\Models\Profil;
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
        
        $query = User::with(['profil', 'roles']);

        // Filtre par recherche (nom, email, et autres champs pertinents)
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhereHas('profil', function($subQ) use ($search) {
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
            $query->where('is_active', (bool) $request->activation);
        }

        // Filtre par rôle
        if ($request->has('role') && $request->role) {
            $query->whereHas('roles', function($q) use ($request) {
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
        
        return Inertia::render('users/Create', [
            'roles' => $roles,
            'departments' => $departments,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'fonction' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role_id' => 'required|integer|exists:roles,id',
            'department_id' => 'nullable|integer|exists:departments,id',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'fonction' => $validated['fonction'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Attacher le rôle unique
        $user->roles()->sync([$validated['role_id']]);
        $this->syncUserProfileType($user, $validated['role_id']);
        $user->save();

        $this->syncProfilDepartment($user, $validated['department_id'] ?? null);

        return redirect()->route('users.index')
            ->with('success', 'Utilisateur créé avec succès !');
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        $user->load(['profil', 'roles']);
        
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
        $user->load(['roles', 'profil']);
        
        return Inertia::render('users/Edit', [
            'user' => $user,
            'roles' => $roles,
            'departments' => $departments,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'fonction' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'role_id' => 'required|integer|exists:roles,id',
            'department_id' => 'nullable|integer|exists:departments,id',
        ]);

        $data = [
            'name' => $validated['name'],
            'fonction' => $validated['fonction'],
            'email' => $validated['email'],
        ];

        // Mettre à jour le mot de passe seulement s'il est fourni
        if (!empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user->update($data);

        // Synchroniser le rôle unique
        $user->roles()->sync([$validated['role_id']]);
        $this->syncUserProfileType($user, $validated['role_id']);
        $user->save();

        $this->syncProfilDepartment($user, $validated['department_id'] ?? null);

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
        $user->is_active = !$user->is_active;
        $user->save();

        $status = $user->is_active ? 'activé' : 'désactivé';
        
        return redirect()->route('users.index')
            ->with('success', "Utilisateur {$status} avec succès !");
    }

    private function syncProfilDepartment(User $user, ?int $departmentId): void
    {
        if ($departmentId === null) {
            return;
        }

        $department = Department::find($departmentId);
        if (!$department) {
            return;
        }

        $profile = Profil::firstOrNew(['email' => $user->email]);

        if (!$profile->prenom || !$profile->nom) {
            $parts = preg_split('/\s+/', trim($user->name));
            $profile->prenom = $profile->prenom ?: ($parts[0] ?? null);
            $profile->nom = $profile->nom ?: (count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : null);
        }

        $profile->fonction = $user->fonction;
        $profile->departement = $department->name;
        $profile->department_id = $department->id;
        $profile->email = $user->email;

        $profile->save();
    }

    private function syncUserProfileType(User $user, int $roleId): void
    {
        $role = Role::find($roleId);
        $adminSlugs = ['it', 'admin'];

        $user->profile = ($role && in_array($role->slug, $adminSlugs, true)) ? 'admin' : 'other';
    }
}

