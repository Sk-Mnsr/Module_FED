<?php

namespace App\Imports;

use App\Models\Agence;
use App\Models\Department;
use App\Models\Profil;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class UserImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $row = $row->toArray();

            $email = $this->cell($row, ['email']);
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
            $matricule = $rawMatricule !== '' ? $rawMatricule : null;

            $passwordPlain = $this->cell($row, ['mot_de_passe', 'password', 'mot_de_passe_initial']);

            $departmentId = $this->resolveDepartmentId($this->cell($row, ['departement', 'department']));
            $agenceId = $this->resolveAgenceId($this->cell($row, ['code_agence', 'agence_code', 'agence']));

            $attributes = [
                'name' => $name,
                'fonction' => $fonction,
                'matricule' => $matricule,
                'agence_id' => $agenceId,
                'department_id' => $departmentId,
                'activated' => true,
            ];

            $user = User::firstOrNew(['email' => $email]);
            $isNew = ! $user->exists;

            if ($isNew) {
                $attributes['password'] = Hash::make($passwordPlain !== '' ? $passwordPlain : Str::password(12));
                $attributes['password_change_required'] = true;
            } elseif ($passwordPlain !== '') {
                $attributes['password'] = Hash::make($passwordPlain);
                $attributes['password_change_required'] = true;
            }

            $user->fill($attributes);
            $user->save();

            $user->roles()->sync([$role->id]);
            $this->syncUserProfileType($user, $role);
            $user->save();
            $this->syncUserProfil($user);
        }
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

        $normalized = Str::lower($value);

        return Role::query()
            ->where('actif', true)
            ->where(function ($q) use ($normalized, $value) {
                $q->whereRaw('LOWER(slug) = ?', [$normalized])
                    ->orWhereRaw('LOWER(nom) = ?', [Str::lower($value)]);
            })
            ->first();
    }

    private function resolveDepartmentId(string $value): ?int
    {
        if ($value === '') {
            return null;
        }

        $department = Department::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower($value)])
            ->first();

        return $department?->id;
    }

    private function resolveAgenceId(string $value): ?int
    {
        if ($value === '') {
            return null;
        }

        $agence = Agence::query()
            ->where('code', $value)
            ->orWhereRaw('LOWER(nom) = ?', [Str::lower($value)])
            ->first();

        return $agence?->id;
    }

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

    private function syncUserProfileType(User $user, Role $role): void
    {
        $adminSlugs = ['it', 'admin'];

        if (in_array($role->slug, $adminSlugs, true)) {
            $user->profile = 'admin';

            return;
        }

        if (in_array($role->slug, ['monetique', 'monetique_ops', 'ca', 'cc', 'caissier'], true)) {
            $user->profile = 'monetique';

            return;
        }

        $user->profile = 'other';
    }
}
