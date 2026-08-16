<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserModuleAbility;

/**
 * Droits granulaires par module (ex. Budget : view / create / update / delete / import).
 */
final class ModuleAbilities
{
    public const BUDGET = 'budget';

    /** @var array<string, list<string>> */
    private const DEFINITIONS = [
        self::BUDGET => ['view', 'create', 'update', 'delete', 'import'],
    ];

    /** @var array<string, string> */
    private const LABELS = [
        'view' => 'Consultation',
        'create' => 'Ajouter',
        'update' => 'Modifier',
        'delete' => 'Supprimer',
        'import' => 'Importer / Exporter',
    ];

    /**
     * @return list<string>
     */
    public static function keysFor(string $module): array
    {
        return self::DEFINITIONS[$module] ?? [];
    }

    public static function hasGranularAbilities(string $module): bool
    {
        return self::keysFor($module) !== [];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function optionsFor(string $module): array
    {
        return array_map(
            fn (string $key) => [
                'key' => $key,
                'label' => self::LABELS[$key] ?? $key,
            ],
            self::keysFor($module),
        );
    }

    /**
     * @return array<string, list<array{key: string, label: string}>>
     */
    public static function catalog(): array
    {
        $out = [];
        foreach (array_keys(self::DEFINITIONS) as $module) {
            $out[$module] = self::optionsFor($module);
        }

        return $out;
    }

    /**
     * @return array<string, bool>
     */
    public static function defaults(string $module, bool $enabled = false): array
    {
        $abilities = [];
        foreach (self::keysFor($module) as $key) {
            $abilities[$key] = $enabled && $key === 'view';
        }

        return $abilities;
    }

    /**
     * @return array<string, bool>
     */
    public static function allEnabled(string $module): array
    {
        $abilities = [];
        foreach (self::keysFor($module) as $key) {
            $abilities[$key] = true;
        }

        return $abilities;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, bool>
     */
    public static function normalize(string $module, array $input): array
    {
        $normalized = self::defaults($module);
        foreach (self::keysFor($module) as $key) {
            $normalized[$key] = filter_var($input[$key] ?? false, FILTER_VALIDATE_BOOL);
        }

        // Accès module sans aucun droit → au moins la consultation.
        if (in_array(true, $normalized, true) === false) {
            $normalized['view'] = true;
        }

        // Tout droit d’écriture implique la consultation.
        if ($normalized['create'] || $normalized['update'] || $normalized['delete'] || ($normalized['import'] ?? false)) {
            $normalized['view'] = true;
        }

        return $normalized;
    }

    /**
     * @return array<string, bool>
     */
    public static function forUser(User $user, string $module): array
    {
        if (! self::hasGranularAbilities($module)) {
            return [];
        }

        if (ModuleAccess::isAdminUser($user)) {
            return self::allEnabled($module);
        }

        if (! ModuleAccess::userCanAccess($user, $module)) {
            return self::defaults($module);
        }

        $row = UserModuleAbility::query()
            ->where('user_id', $user->id)
            ->where('module', $module)
            ->first();

        if ($row === null) {
            // Compat : accès module sans ligne d’abilities → CRUD complet.
            return self::allEnabled($module);
        }

        return self::normalize($module, is_array($row->abilities) ? $row->abilities : []);
    }

    public static function userCan(User $user, string $module, string $ability): bool
    {
        if (ModuleAccess::isAdminUser($user)) {
            return true;
        }

        if (! ModuleAccess::userCanAccess($user, $module)) {
            return false;
        }

        $abilities = self::forUser($user, $module);

        return (bool) ($abilities[$ability] ?? false);
    }

    /**
     * @param  array<string, array<string, mixed>>  $byModule
     */
    public static function syncForUser(User $user, array $byModule): void
    {
        foreach (array_keys(self::DEFINITIONS) as $module) {
            $hasModuleAccess = ModuleAccess::userCanAccess($user, $module);

            if (! $hasModuleAccess) {
                UserModuleAbility::query()
                    ->where('user_id', $user->id)
                    ->where('module', $module)
                    ->delete();

                continue;
            }

            if (ModuleAccess::isAdminUser($user)) {
                UserModuleAbility::query()->updateOrCreate(
                    ['user_id' => $user->id, 'module' => $module],
                    ['abilities' => self::allEnabled($module)],
                );

                continue;
            }

            $abilities = self::normalize($module, is_array($byModule[$module] ?? null) ? $byModule[$module] : []);

            UserModuleAbility::query()->updateOrCreate(
                ['user_id' => $user->id, 'module' => $module],
                ['abilities' => $abilities],
            );
        }
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public static function payloadForUser(User $user): array
    {
        $out = [];
        foreach (array_keys(self::DEFINITIONS) as $module) {
            $out[$module] = self::forUser($user, $module);
        }

        return $out;
    }
}
