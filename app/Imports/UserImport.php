<?php

namespace App\Imports;

use App\Models\Agence;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleAccessProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class UserImport implements ToCollection, WithHeadingRow
{
    /** @var array<string, Role> */
    private array $rolesByKey = [];

    /** @var array<string, int> */
    private array $agencesByKey = [];

    /** @var array<string, int> */
    private array $departmentsByKey = [];

    /** @var array<string, User> */
    private array $usersByEmail = [];

    /** @var array<string, User> */
    private array $usersByMatricule = [];

    private ?string $defaultPasswordHash = null;

    public function collection(Collection $rows): void
    {
        $this->warmCaches();

        foreach ($rows as $row) {
            $row = $row->toArray();

            $email = $this->normalizeEmail($this->cell($row, ['email']));
            $name = $this->cell($row, ['nom', 'name']);
            $fonction = $this->cell($row, ['fonction']);

            if ($email === '' || $name === '' || $fonction === '') {
                continue;
            }

            $role = $this->resolveRole($this->cell($row, ['role', 'role_slug']));
            if (! $role) {
                continue;
            }

            $rawMatricule = $this->cell($row, ['idflex', 'matricule']);
            $matricule = $rawMatricule !== '' ? Str::upper($rawMatricule) : null;

            $passwordPlain = $this->cell($row, ['mot_de_passe', 'password', 'mot_de_passe_initial']);

            $departmentId = $this->resolveDepartmentId($this->cell($row, ['departement', 'department']));
            $agenceId = $this->resolveAgenceId($this->cell($row, ['code_agence', 'agence_code', 'agence']));

            $attributes = [
                'name' => $name,
                'fonction' => $fonction,
                'agence_id' => $agenceId,
                'department_id' => $departmentId,
                'activated' => true,
                'profile' => RoleAccessProfile::forRole($role),
            ];

            if ($matricule !== null) {
                $attributes['matricule'] = $matricule;
            }

            $user = $this->resolveUser($email, $matricule);
            $isNew = ! $user->exists;

            if ($isNew) {
                $user->email = $email;
                $attributes['password'] = $passwordPlain !== ''
                    ? Hash::make($passwordPlain)
                    : $this->defaultPasswordHash();
                $attributes['password_change_required'] = true;
            } elseif ($passwordPlain !== '') {
                $attributes['password'] = Hash::make($passwordPlain);
                $attributes['password_change_required'] = true;
            }

            if ($user->exists && $user->email !== $email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                unset($this->usersByEmail[Str::lower($user->email)]);
                $user->email = $email;
            }

            $user->fill($attributes);
            $user->save();

            $user->roles()->sync([$role->id]);
            $this->registerUserInCache($user);
        }
    }

    private function warmCaches(): void
    {
        foreach (Role::query()->where('actif', true)->get() as $role) {
            $this->rolesByKey[Str::lower($role->slug)] = $role;
            $this->rolesByKey[Str::lower($role->nom)] = $role;
        }

        foreach (Agence::query()->get(['id', 'code', 'nom']) as $agence) {
            $this->agencesByKey[(string) $agence->code] = $agence->id;
            $this->agencesByKey[Str::lower($agence->nom)] = $agence->id;
        }

        foreach (Department::query()->get(['id', 'name']) as $department) {
            $this->departmentsByKey[Str::lower($department->name)] = $department->id;
        }

        foreach (User::query()->get() as $user) {
            $this->registerUserInCache($user);
        }
    }

    private function defaultPasswordHash(): string
    {
        return $this->defaultPasswordHash ??= Hash::make(Str::password(16));
    }

    private function registerUserInCache(User $user): void
    {
        if ($user->email) {
            $this->usersByEmail[Str::lower($user->email)] = $user;
        }
        if ($user->matricule) {
            $this->usersByMatricule[Str::upper($user->matricule)] = $user;
        }
    }

    private function normalizeEmail(string $email): string
    {
        $email = Str::lower(trim($email));
        $email = (string) preg_replace('/,([a-z]{2,})$/', '.$1', $email);

        return $email;
    }

    private function resolveUser(string $email, ?string $matricule): User
    {
        $user = $this->usersByEmail[$email] ?? null;

        if ($user === null && $matricule !== null) {
            $user = $this->usersByMatricule[$matricule] ?? null;
        }

        return $user ?? new User;
    }

    private function cell(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }
            $value = $row[$key];
            if ($value !== null && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return '';
    }

    private function resolveRole(string $value): ?Role
    {
        if ($value === '') {
            return null;
        }

        return $this->rolesByKey[Str::lower($value)] ?? null;
    }

    private function resolveDepartmentId(string $value): ?int
    {
        if ($value === '') {
            return null;
        }

        return $this->departmentsByKey[Str::lower($value)] ?? null;
    }

    private function resolveAgenceId(string $value): ?int
    {
        if ($value === '') {
            return null;
        }

        return $this->agencesByKey[$value]
            ?? $this->agencesByKey[Str::lower($value)]
            ?? null;
    }
}
