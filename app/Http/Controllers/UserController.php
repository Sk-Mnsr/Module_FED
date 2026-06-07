<?php

namespace App\Http\Controllers;

use App\Exports\UserTemplateExport;
use App\Imports\UserImport;
use App\Models\Agence;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Support\ModuleAccess;
use App\Support\RoleAccessProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 5);

        $query = User::with(['roles', 'agence', 'department', 'nPlus1']);

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
                    ->orWhere('fonction', 'like', "%{$search}%");
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
        $roles = Role::where('actif', true)->orderBy('module')->orderBy('nom')->get(['id', 'nom', 'module']);

        return Inertia::render('users/Index', [
            'users' => $users,
            'roles' => $roles,
            'modules' => ModuleAccess::moduleOptions(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('users/Create', $this->userFormProps());
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
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
            'department_id' => 'nullable|integer|exists:departments,id',
            'agence_id' => 'nullable|integer|exists:agences,id',
            'matricule' => [
                'nullable',
                'string',
                'max:128',
                Rule::unique('users', 'matricule'),
            ],
            'n_plus_1_user_id' => 'nullable|integer|exists:users,id',
            'n_plus_2_user_id' => 'nullable|integer|exists:users,id',
        ]);

        $rawMatricule = $validated['matricule'] ?? null;
        $matricule = $rawMatricule !== null ? trim((string) $rawMatricule) : '';
        $matricule = $matricule === '' ? null : $matricule;

        $roleIds = $this->validatedRoleIds($validated['role_ids']);

        $user = User::create([
            'name' => $validated['name'],
            'fonction' => $validated['fonction'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'agence_id' => $validated['agence_id'] ?? null,
            'matricule' => $matricule,
            'department_id' => $validated['department_id'] ?? null,
            'n_plus_1_user_id' => $validated['n_plus_1_user_id'] ?? null,
            'n_plus_2_user_id' => $validated['n_plus_2_user_id'] ?? null,
            'password_change_required' => true,
        ]);

        // Attacher les rôles (un par module)
        $user->roles()->sync($roleIds);
        $user->profile = RoleAccessProfile::forRoleIds($roleIds);
        $user->save();

        return redirect()->route('users.index')
            ->with('success', 'Utilisateur créé avec succès !');
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        $user->load(['roles', 'agence', 'department', 'nPlus1', 'nPlus2']);

        return Inertia::render('users/Show', [
            'user' => $user,
            'modules' => ModuleAccess::moduleOptions(),
            'accessibleModules' => ModuleAccess::accessibleModuleKeys($user),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        $user->load(['roles', 'agence', 'department', 'nPlus1', 'nPlus2']);

        return Inertia::render('users/Edit', [
            ...$this->userFormProps(),
            'user' => $user,
            'supervisors' => $this->supervisorOptions($user->id),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        if (! $request->filled('matricule')) {
            $request->merge(['matricule' => null]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'fonction' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
            'department_id' => 'nullable|integer|exists:departments,id',
            'agence_id' => 'nullable|integer|exists:agences,id',
            'matricule' => [
                'nullable',
                'string',
                'max:128',
                Rule::unique('users', 'matricule')->ignore($user->id),
            ],
            'n_plus_1_user_id' => 'nullable|integer|exists:users,id|not_in:'.$user->id,
            'n_plus_2_user_id' => 'nullable|integer|exists:users,id|not_in:'.$user->id,
        ]);

        $rawMatricule = $validated['matricule'] ?? null;
        $matricule = $rawMatricule !== null ? trim((string) $rawMatricule) : '';
        $matricule = $matricule === '' ? null : $matricule;

        $roleIds = $this->validatedRoleIds($validated['role_ids']);

        $data = [
            'name' => $validated['name'],
            'fonction' => $validated['fonction'],
            'email' => $validated['email'],
            'agence_id' => $validated['agence_id'] ?? null,
            'matricule' => $matricule,
            'department_id' => $validated['department_id'] ?? null,
            'n_plus_1_user_id' => $validated['n_plus_1_user_id'] ?? null,
            'n_plus_2_user_id' => $validated['n_plus_2_user_id'] ?? null,
        ];

        // Mettre à jour le mot de passe seulement s'il est fourni
        if (! empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
            $data['password_change_required'] = true;
        }

        $user->update($data);

        // Synchroniser les rôles (un par module)
        $user->roles()->sync($roleIds);
        $user->profile = RoleAccessProfile::forRoleIds($roleIds);
        $user->save();

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

    public function exportTemplate()
    {
        return Excel::download(new UserTemplateExport, 'template_utilisateurs.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:10240',
        ]);

        set_time_limit(300);

        Excel::import(new UserImport, $request->file('file'));

        return redirect()->route('users.index')
            ->with('success', 'Utilisateurs importés avec succès.');
    }

    /**
     * Toggle the active status of a user.
     */
    public function toggle(User $user)
    {
        $user->activated = ! $user->activated;
        $user->save();

        $status = $user->activated ? 'activé' : 'désactivé';

        return redirect()->route('users.index')
            ->with('success', "Utilisateur {$status} avec succès !");
    }

    /**
     * @param  list<int>  $roleIds
     * @return list<int>
     */
    private function validatedRoleIds(array $roleIds): array
    {
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));
        $roles = Role::whereIn('id', $roleIds)->get(['id', 'module', 'slug', 'access_profile']);

        if ($roles->count() !== count($roleIds)) {
            throw ValidationException::withMessages([
                'role_ids' => 'Un ou plusieurs rôles sélectionnés sont invalides.',
            ]);
        }

        $modules = $roles->pluck('module')->filter()->values();
        if ($modules->count() !== $modules->unique()->count()) {
            throw ValidationException::withMessages([
                'role_ids' => 'Un seul rôle par module est autorisé.',
            ]);
        }

        return $roleIds;
    }

    /**
     * @return array<string, mixed>
     */
    private function userFormProps(): array
    {
        return [
            'roles' => Role::where('actif', true)
                ->orderBy('module')
                ->orderBy('nom')
                ->get(['id', 'nom', 'slug', 'module', 'access_profile', 'description']),
            'modules' => ModuleAccess::moduleOptions(),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'agences' => Agence::orderBy('nom')->get(['id', 'code', 'nom']),
            'supervisors' => $this->supervisorOptions(),
        ];
    }

    /**
     * @return list<array{id: int, name: string, email: string}>
     */
    private function supervisorOptions(?int $excludeUserId = null): array
    {
        return User::query()
            ->when($excludeUserId !== null, fn ($q) => $q->where('id', '!=', $excludeUserId))
            ->where('activated', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->values()
            ->all();
    }
}
