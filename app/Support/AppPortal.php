<?php

namespace App\Support;

use App\Models\User;

final class AppPortal
{
    /** @var array<string, string> */
    private const ICONS = [
        'fed' => 'file-text',
        'budget' => 'calculator',
        'stock' => 'table-2',
        'ecritures' => 'layers',
        'monetique' => 'credit-card',
        'od' => 'file-spreadsheet',
        'config' => 'settings',
    ];

    /** @var array<string, string> */
    private const DESCRIPTIONS = [
        'fed' => 'Demandes de dépense, circuits de validation et achats.',
        'budget' => 'Consultation et suivi des budgets par service.',
        'stock' => 'Demandes d\'approvisionnement et gestion des stocks.',
        'ecritures' => 'Import, consultation et export des écritures comptables.',
        'monetique' => 'Coficarte, ventes, recharges, transferts et encaissements.',
        'od' => 'Intégration automatique ou manuelle des opérations diverses.',
        'config' => 'Paramétrage et administration de la plateforme.',
    ];

    /** @var array<string, string> */
    private const ACCENTS = [
        'fed' => 'rose',
        'budget' => 'emerald',
        'stock' => 'amber',
        'ecritures' => 'blue',
        'monetique' => 'violet',
        'od' => 'orange',
        'config' => 'slate',
    ];

    /** @var array<string, list<string>> */
    private const PATH_PREFIXES = [
        'fed' => [
            'feds', 'appel-offres', 'achats', 'bons-de-commande', 'comites',
        ],
        'budget' => ['budgets'],
        'stock' => ['demandes-approvisionnement', 'stock'],
        'ecritures' => ['ecritures-comptables'],
        'monetique' => ['monetique'],
        'od' => ['operations-diverses'],
        'config' => [
            'users', 'roles', 'departments', 'typologies', 'categories', 'banques',
            'fournisseurs', 'type-depenses', 'fiche-integrations', 'agences',
            'apporteurs-affaires', 'articles', 'familles', 'settings',
        ],
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public static function cardsForUser(User $user): array
    {
        $cards = [];

        foreach (self::portalModuleKeys($user) as $key) {
            if (AppNavigation::moduleNavGroups($user, $key) === []) {
                continue;
            }

            $cards[] = [
                'key' => $key,
                'label' => ModuleAccess::modules()[$key]['label'] ?? strtoupper($key),
                'description' => self::DESCRIPTIONS[$key] ?? '',
                'accent' => self::ACCENTS[$key] ?? 'slate',
                'icon' => self::ICONS[$key] ?? 'layout-grid',
                'entry_url' => route('portal.enter', $key),
            ];
        }

        return $cards;
    }

    /**
     * @return list<array{label: string, href: string}>
     */
    public static function adminLinksForUser(User $user): array
    {
        if (! ModuleAccess::isAdminUser($user)) {
            return [];
        }

        return [
            ['label' => 'Utilisateurs', 'href' => '/users'],
            ['label' => 'Rôles', 'href' => '/roles'],
            ['label' => 'Paramètres applicatifs', 'href' => '/settings/app'],
        ];
    }

    public static function moduleKeyFromPath(string $path): ?string
    {
        $segment = explode('/', trim($path, '/'))[0] ?? '';

        if ($segment === '') {
            return null;
        }

        foreach (self::PATH_PREFIXES as $module => $prefixes) {
            if (in_array($segment, $prefixes, true)) {
                return $module;
            }
        }

        return null;
    }

    public static function entryUrl(User $user, string $moduleKey): string
    {
        $groups = AppNavigation::moduleNavGroups($user, $moduleKey);

        return self::firstHrefInGroups($groups) ?? route('portal');
    }

    /**
     * @return list<string>
     */
    private static function portalModuleKeys(User $user): array
    {
        return ModuleAccess::accessibleModuleKeys($user);
    }

    /**
     * @param  list<array{label?: string, items: list<array<string, mixed>>}>  $groups
     */
    private static function firstHrefInGroups(array $groups): ?string
    {
        foreach ($groups as $group) {
            $href = self::firstHrefInItems($group['items'] ?? []);
            if ($href !== null) {
                return $href;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private static function firstHrefInItems(array $items): ?string
    {
        foreach ($items as $item) {
            if (! empty($item['href'])) {
                return (string) $item['href'];
            }

            if (! empty($item['items'])) {
                $href = self::firstHrefInItems($item['items']);
                if ($href !== null) {
                    return $href;
                }
            }
        }

        return null;
    }
}
