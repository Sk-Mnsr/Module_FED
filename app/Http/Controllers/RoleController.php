<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Support\ModuleAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 15);

        $query = Role::query()->withCount('users');

        if ($request->filled('module')) {
            $query->where(function ($q) use ($request) {
                $q->where('module', $request->module);

                if (Schema::hasTable('role_module')) {
                    $q->orWhereExists(function ($sub) use ($request) {
                        $sub->select(DB::raw(1))
                            ->from('role_module')
                            ->whereColumn('role_module.role_id', 'roles.id')
                            ->where('role_module.module', $request->module);
                    });
                }
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $roles = $query->orderBy('module')->orderBy('nom')->paginate($perPage);
        $roles->getCollection()->transform(function (Role $role) {
            $role->setAttribute('module_keys', $role->moduleKeys());

            return $role;
        });

        return Inertia::render('roles/Index', [
            'roles' => $roles,
            'modules' => ModuleAccess::moduleOptions(),
            'moduleMatrix' => ModuleAccess::moduleMatrix(),
            'accessProfiles' => $this->accessProfileOptions(),
            'filters' => [
                'search' => $request->search ?? '',
                'module' => $request->module ?? '',
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('roles/Create', $this->formOptions());
    }

    public function store(Request $request)
    {
        [$attributes, $moduleKeys] = $this->validatedRolePayload($request);

        DB::transaction(function () use ($attributes, $moduleKeys) {
            $role = Role::create($attributes);
            $role->syncModuleKeys($moduleKeys);
        });

        return redirect()->route('roles.index')
            ->with('success', 'Rôle créé avec succès.');
    }

    public function edit(Role $role)
    {
        return Inertia::render('roles/Edit', [
            ...$this->formOptions(),
            'role' => [
                ...$role->toArray(),
                'module_keys' => ModuleAccess::defaultModuleKeysForRole($role),
            ],
        ]);
    }

    public function update(Request $request, Role $role)
    {
        if ($role->slug === 'it') {
            $request->merge(['slug' => 'it']);
        }

        [$attributes, $moduleKeys] = $this->validatedRolePayload($request, $role);

        DB::transaction(function () use ($role, $attributes, $moduleKeys) {
            $role->update($attributes);
            $role->syncModuleKeys($moduleKeys);
        });

        return redirect()->route('roles.index')
            ->with('success', 'Rôle mis à jour avec succès.');
    }

    public function destroy(Role $role)
    {
        if ($role->slug === 'it') {
            return redirect()->route('roles.index')
                ->with('error', 'Le rôle SuperAdmin (IT) ne peut pas être supprimé.');
        }

        if ($role->users()->exists()) {
            return redirect()->route('roles.index')
                ->with('error', 'Ce rôle est assigné à des utilisateurs. Désactivez-le ou réassignez les utilisateurs avant suppression.');
        }

        $role->delete();
        ModuleAccess::clearModuleRolesCache();

        return redirect()->route('roles.index')
            ->with('success', 'Rôle supprimé avec succès.');
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function validatedRolePayload(Request $request, ?Role $role = null): array
    {
        $moduleKeysAllowed = array_column(ModuleAccess::moduleOptions(), 'key');

        $validated = $request->validate([
            'nom' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'nom')->ignore($role?->id),
            ],
            'slug' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('roles', 'slug')->ignore($role?->id),
            ],
            'module' => ['required', 'string', Rule::in($moduleKeysAllowed)],
            'module_keys' => ['required', 'array', 'min:1'],
            'module_keys.*' => ['string', Rule::in($moduleKeysAllowed)],
            'access_profile' => ['required', 'string', Rule::in(['admin', 'monetique', 'other'])],
            'description' => ['nullable', 'string', 'max:2000'],
            'actif' => ['boolean'],
        ]);

        $moduleKeys = array_values(array_unique($validated['module_keys']));

        if (! in_array($validated['module'], $moduleKeys, true)) {
            throw ValidationException::withMessages([
                'module' => 'Le module principal doit faire partie des modules accessibles.',
            ]);
        }

        $attributes = [
            'nom' => $validated['nom'],
            'slug' => $validated['slug'],
            'module' => $validated['module'],
            'access_profile' => $validated['access_profile'],
            'description' => $validated['description'] ?? null,
            'actif' => $request->boolean('actif'),
        ];

        return [$attributes, $moduleKeys];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'modules' => ModuleAccess::moduleOptions(),
            'accessProfiles' => $this->accessProfileOptions(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function accessProfileOptions(): array
    {
        return [
            ['value' => 'admin', 'label' => 'Administrateur'],
            ['value' => 'monetique', 'label' => 'Monétique'],
            ['value' => 'other', 'label' => 'Métier'],
        ];
    }
}
