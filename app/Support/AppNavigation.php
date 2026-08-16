<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AppNavigation
{
    /**
     * @return list<array{label?: string, module?: string, items: list<array<string, mixed>>}>
     */
    public static function groups(?User $user, ?string $activeModule = null, bool $onPortal = false): array
    {
        if ($user === null) {
            return [];
        }

        if ($onPortal) {
            return [self::portalNavGroup()];
        }

        $nav = [self::portalNavGroup()];

        if ($activeModule === null) {
            return $nav;
        }

        $moduleGroups = self::moduleNavGroups($user, $activeModule);
        if ($moduleGroups === []) {
            return $nav;
        }

        return array_merge($nav, $moduleGroups);
    }

    /**
     * @return list<array{label?: string, items: list<array<string, mixed>>}>
     */
    public static function moduleNavGroups(User $user, string $moduleKey): array
    {
        return array_map(
            fn (array $group) => [
                'label' => $group['label'] ?? null,
                'items' => $group['items'],
            ],
            self::groupsForModuleKey($user, $moduleKey),
        );
    }

    /**
     * @return list<string>
     */
    public static function sectionTitlesForModule(User $user, string $moduleKey): array
    {
        $titles = [];

        foreach (self::groupsForModuleKey($user, $moduleKey) as $group) {
            foreach ($group['items'] as $item) {
                if (! empty($item['items']) && ! empty($item['title'])) {
                    $titles[] = (string) $item['title'];
                } elseif (! empty($item['href']) && ! empty($item['title'])) {
                    $titles[] = (string) $item['title'];
                }
            }
        }

        return array_values(array_unique($titles));
    }

    /**
     * @return list<array{label?: string, module: string, items: list<array<string, mixed>>}>
     */
    private static function groupsForModuleKey(User $user, string $moduleKey): array
    {
        $groups = array_values(array_filter(
            self::allTaggedGroups($user),
            fn (array $group) => ($group['module'] ?? null) === $moduleKey,
        ));

        if (
            $moduleKey === 'fed'
            && ! in_array('stock', ModuleAccess::accessibleModuleKeys($user), true)
        ) {
            $stockGroups = array_values(array_filter(
                self::allTaggedGroups($user),
                fn (array $group) => ($group['module'] ?? null) === 'stock',
            ));
            $groups = array_merge($groups, $stockGroups);
        }

        return $groups;
    }

    /**
     * @return list<array{label?: string, module: string, items: list<array<string, mixed>>}>
     */
    private static function allTaggedGroups(User $user): array
    {
        $user->loadMissing('roles');

        $roleSlugs = ModuleAccess::normalizedRoleSlugs($user);
        $modules = ModuleAccess::accessibleModuleKeys($user);
        $hasConfigAccess = ModuleAccess::isAdminUser($user);
        $isInCommittee = self::userIsInCommittee($user->id);
        $hasModule = fn (string $module): bool => in_array($module, $modules, true);

        $groups = [];

        if ($hasModule('fed')) {
            $fedItems = self::fedItems($roleSlugs, $isInCommittee, $hasConfigAccess);
            if ($fedItems !== []) {
                $groups[] = ['module' => 'fed', 'label' => 'FED', 'items' => $fedItems];
            }
        }

        if ($hasModule('budget')) {
            $budgetItems = self::budgetItems($roleSlugs, $hasConfigAccess);
            if ($budgetItems !== []) {
                $groups[] = ['module' => 'budget', 'label' => 'Budget', 'items' => $budgetItems];
            }
        }

        if ($hasModule('stock') || $hasModule('fed')) {
            $stockItems = self::stockItems($roleSlugs, $hasConfigAccess, $hasModule('stock'));
            if ($stockItems !== []) {
                $groups[] = [
                    'module' => 'stock',
                    'label' => 'Gestion de stock',
                    'items' => $stockItems,
                ];
            }
        }

        $ecrituresItems = self::ecrituresItems($modules);
        if ($ecrituresItems !== []) {
            $groups[] = ['module' => 'ecritures', 'label' => 'Écritures comptables', 'items' => $ecrituresItems];
        }

        $monetiqueItems = self::monetiqueItems($user, $roleSlugs, $modules, $hasConfigAccess);
        if ($monetiqueItems !== []) {
            $groups[] = ['module' => 'monetique', 'label' => 'Monétique', 'items' => $monetiqueItems];
        }

        $odItems = self::odItems($modules);
        if ($odItems !== []) {
            $groups[] = ['module' => 'od', 'label' => 'Opérations diverses', 'items' => $odItems];
        }

        $reconciliationItems = self::reconciliationItems($modules);
        if ($reconciliationItems !== []) {
            $groups[] = [
                'module' => 'reconciliation',
                'label' => 'Réconciliation Flexcube',
                'items' => $reconciliationItems,
            ];
        }

        $configItems = self::configItems($hasModule('config'));
        if ($configItems !== []) {
            $groups[] = ['module' => 'config', 'label' => 'Référentiels', 'items' => $configItems];
        }

        return $groups;
    }

    /**
     * @return array{items: list<array<string, mixed>>}
     */
    private static function portalNavGroup(): array
    {
        return [
            'items' => [
                self::link('Modules', route('portal'), 'layout-grid'),
            ],
        ];
    }

    /**
     * @param  list<string>  $roleSlugs
     * @return list<array<string, mixed>>
     */
    private static function fedItems(array $roleSlugs, bool $isInCommittee, bool $hasConfigAccess): array
    {
        $hasRole = fn (string $slug): bool => in_array($slug, $roleSlugs, true);
        $canSee = fn (string $slug): bool => $hasConfigAccess || $hasRole($slug);

        $items = [
            self::section('Fiches de dépense', 'file-text', [
                self::link('Nouvelle demande', '/feds/create'),
                self::link('Mes demandes', '/feds'),
            ]),
        ];

        $validationItems = self::compact([
            $canSee('n_plus_1') ? self::link('N+1', '/feds/n1') : null,
            $canSee('responsable_facilities') ? self::link('Facilities', '/feds/facilities') : null,
            $canSee('controle_de_gestion') ? self::link('Contrôle de gestion', '/feds/cg') : null,
            $canSee('daf') ? self::link('DAF', '/feds/daf') : null,
            $canSee('dga') ? self::link('DGA', '/feds/dga') : null,
        ]);

        if ($validationItems !== []) {
            $items[] = self::section('Validations', 'shield', $validationItems);
        }

        $achatsItems = self::compact([
            $canSee('responsable_achats') ? self::link('Demandes en cours', '/feds/achats') : null,
            $canSee('responsable_achats') ? self::link('TDR', '/appel-offres') : null,
            $canSee('responsable_achats') ? self::link('Tableaux comparatifs', '/achats/tableaux-comparatifs') : null,
            $canSee('responsable_achats') ? self::link('Fournisseurs', '/fournisseurs') : null,
            ($canSee('responsable_achats') || $canSee('dga'))
                ? self::link('Bons de commande', '/bons-de-commande')
                : null,
            ($canSee('responsable_achats') || $isInCommittee || $hasConfigAccess)
                ? self::link('Comité', '/comites')
                : null,
        ]);

        if ($achatsItems !== []) {
            $items[] = self::section('Achats', 'shopping-cart', $achatsItems);
        }

        return $items;
    }

    /**
     * @param  list<string>  $roleSlugs
     * @return list<array<string, mixed>>
     */
    private static function budgetItems(array $roleSlugs, bool $hasConfigAccess): array
    {
        $href = $hasConfigAccess
            ? '/budgets'
            : (in_array('n_plus_1', $roleSlugs, true) ? '/budgets/n1' : '/budgets');

        return [
            self::link('Budgets', $href, 'credit-card'),
        ];
    }

    /**
     * @param  list<string>  $roleSlugs
     * @return list<array<string, mixed>>
     */
    private static function stockItems(array $roleSlugs, bool $hasConfigAccess, bool $hasStockModule): array
    {
        $hasRole = fn (string $slug): bool => in_array($slug, $roleSlugs, true);

        $items = [];

        if ($hasStockModule || $hasConfigAccess || ! $hasRole('responsable_stock')) {
            $approachItems = [];
            if (! $hasRole('responsable_stock')) {
                $approachItems[] = self::link('Nouvelle demande', '/demandes-approvisionnement/create');
            }
            $approachItems[] = self::link('Liste des demandes', '/demandes-approvisionnement');

            $items[] = self::section('Approvisionnement', 'shopping-cart', $approachItems);
        }

        if ($hasConfigAccess || $hasRole('responsable_achats') || $hasRole('responsable_stock')) {
            $items[] = self::link('Gestion de Stock', '/stock', 'table-2');
        }

        return $items;
    }

    /**
     * @param  list<string>  $modules
     * @return list<array<string, mixed>>
     */
    private static function ecrituresItems(array $modules): array
    {
        if (! in_array('ecritures', $modules, true)) {
            return [];
        }

        return [
            self::link('Écritures Comptables', '/ecritures-comptables', 'file-text'),
        ];
    }

    /**
     * @param  list<string>  $roleSlugs
     * @param  list<string>  $modules
     * @return list<array<string, mixed>>
     */
    private static function monetiqueItems(
        User $user,
        array $roleSlugs,
        array $modules,
        bool $hasConfigAccess,
    ): array {
        $hasRole = fn (string $slug): bool => in_array($slug, $roleSlugs, true);
        $hasMonetiqueModule = in_array('monetique', $modules, true);

        if (! $hasMonetiqueModule) {
            return [];
        }

        $canMonetiqueCentral = CoficarteAgenceAccess::canViewAll($user) || $hasConfigAccess;
        $canResponsableMonetique = CoficarteAgenceAccess::canResponsableMonetique($user) || $hasConfigAccess;
        $canEncaissement = $hasConfigAccess
            || $hasRole('caissier')
            || $hasRole('monetique')
            || $hasRole('it');

        if ($canMonetiqueCentral) {
            $sections = self::coficarteCentralItems($user, $canResponsableMonetique);
        } elseif ($hasRole('ca')) {
            $sections = self::coficarteChefAgenceItems($user, $roleSlugs);
        } else {
            $sections = self::coficarteOperationalItems($user, $roleSlugs);
        }

        if ($canEncaissement) {
            $sections[] = self::section('Caisse', 'banknote', [
                self::link('Encaissement', '/monetique/encaissements'),
            ]);
        }

        return $sections;
    }

    /**
     * @return array{title: string, icon?: string, items: list<array<string, mixed>>}
     */
    private static function monetiqueRechargesSection(User $user): array
    {
        return self::section('Recharges', 'wallet', self::compact([
            CoficarteAgenceAccess::canInitiateCoficarteRecharge($user)
                ? self::link('Nouvelle recharge', '/monetique/recharges/nouveau')
                : null,
            self::link('Historique', '/monetique/recharges/historique'),
        ]));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function coficarteCentralItems(User $user, bool $canResponsableMonetique): array
    {
        $cartesItems = self::compact([
            ...($canResponsableMonetique ? [
                self::link('Ajouter', '/monetique/cartes/ajouter'),
                self::link('Modifier prix', '/monetique/cartes/modifier-prix'),
            ] : []),
            self::link('En stock', '/monetique/cartes/en-stock'),
            self::link('Vendues', '/monetique/cartes/vendus'),
        ]);

        return [
            self::section('Pilotage', 'gauge', [
                self::link('Tableau de bord', '/monetique/pilotage'),
                self::link('Campagnes', '/monetique/campagnes'),
                self::link('Seuils & objectifs', '/monetique/parametrage/seuils-stock'),
            ]),
            self::section('Cartes', 'credit-card', $cartesItems),
            self::section('Transferts', 'arrow-left-right', [
                self::link("Demandes d'agences", '/monetique/demandes-approvisionnement'),
                self::link('Nouveau transfert', '/monetique/transferts/nouveau'),
                self::link('En attente', '/monetique/transferts/en-attente'),
                self::link('Historique', '/monetique/transferts/historique'),
            ]),
            self::section('Ventes', 'shopping-cart', [
                self::link('Nouvelle vente', '/monetique/ventes/nouveau'),
                self::link('Historique', '/monetique/ventes/historique'),
            ]),
            self::monetiqueRechargesSection($user),
        ];
    }

    /**
     * @param  list<string>  $roleSlugs
     * @return list<array<string, mixed>>
     */
    private static function coficarteChefAgenceItems(User $user, array $roleSlugs): array
    {
        $hasRole = fn (string $slug): bool => in_array($slug, $roleSlugs, true);

        return [
            self::section('Pilotage', 'gauge', [
                self::link('Vue agence', '/monetique/pilotage'),
                self::link('Suivi ventes & recharges', '/monetique/agence/suivi'),
            ]),
            self::section('Cartes', 'credit-card', [
                self::link('En stock', '/monetique/cartes/en-stock'),
                self::link('Vendues', '/monetique/cartes/vendus'),
            ]),
            self::section('Transferts', 'arrow-left-right', self::compact([
                self::link('Demandes au siège', '/monetique/agence/demandes-approvisionnement'),
                self::link('Réception des cartes', '/monetique/transferts/en-attente'),
                self::link('Retour au siège', '/monetique/agence/retour-cartes'),
                self::link('Approvisionnement CC', '/monetique/agence/approvisionnement-cc'),
                $hasRole('cc')
                    ? self::link('Délester vers le chef d’agence', '/monetique/cc/delester-chef-agence')
                    : null,
                self::link('Historique', '/monetique/transferts/historique'),
            ])),
            self::section('Ventes', 'shopping-cart', self::compact([
                $hasRole('cc') ? self::link('Nouvelle vente', '/monetique/ventes/nouveau') : null,
                self::link('Historique', '/monetique/ventes/historique'),
            ])),
            self::monetiqueRechargesSection($user),
        ];
    }

    /**
     * @param  list<string>  $roleSlugs
     * @return list<array<string, mixed>>
     */
    private static function coficarteOperationalItems(User $user, array $roleSlugs): array
    {
        $hasRole = fn (string $slug): bool => in_array($slug, $roleSlugs, true);

        $sections = [
            self::section('Pilotage', 'gauge', [
                self::link('Indicateurs', '/monetique/pilotage'),
            ]),
            self::section('Cartes', 'credit-card', [
                self::link('En stock', '/monetique/cartes/en-stock'),
                self::link('Vendues', '/monetique/cartes/vendus'),
            ]),
        ];

        if ($hasRole('cc')) {
            $sections[] = self::section('Transferts', 'arrow-left-right', [
                self::link('Délester vers le chef d’agence', '/monetique/cc/delester-chef-agence'),
            ]);
        }

        $sections[] = self::section('Ventes', 'shopping-cart', self::compact([
            CoficarteAgenceAccess::canInitiateCoficarteVente($user)
                ? self::link('Nouvelle vente', '/monetique/ventes/nouveau')
                : null,
            self::link('Historique', '/monetique/ventes/historique'),
        ]));

        $sections[] = self::monetiqueRechargesSection($user);

        return $sections;
    }

    /**
     * @param  list<string>  $modules
     * @return list<array<string, mixed>>
     */
    private static function odItems(array $modules): array
    {
        if (! in_array('od', $modules, true)) {
            return [];
        }

        return [
            self::section('Intégration', 'layers', [
                self::link('Automatique', '/operations-diverses/piece-comptable'),
                self::link('Manuelle', '/operations-diverses/piece-comptable/manuelle'),
                self::link('Mes brouillons', '/operations-diverses/integrations'),
                self::link('En attente', '/operations-diverses/attente-validation'),
            ]),
            self::link('Archivage', '/operations-diverses/archivage', 'archive'),
        ];
    }

    /**
     * @param  list<string>  $modules
     * @return list<array<string, mixed>>
     */
    private static function reconciliationItems(array $modules): array
    {
        if (! in_array('reconciliation', $modules, true)) {
            return [];
        }

        return [
            self::link('Reconciliation', '/reconciliation-flexcube/reconciliation', 'git-compare'),
            self::link('Historique', '/reconciliation-flexcube/historique', 'history'),
            self::link('Partenaires', '/reconciliation-flexcube/partenaires', 'users'),
        ];
    }

    /**
     * Référentiels dépenses (module `config`).
     *
     * @return list<array<string, mixed>>
     */
    private static function configItems(bool $hasAccess): array
    {
        if (! $hasAccess) {
            return [];
        }

        return [
            self::link('Typologies de dépenses', '/typologies', 'tags'),
            self::link('Catégories de dépenses', '/categories', 'folder-tree'),
            self::link('Banques', '/banques', 'landmark'),
            self::link('Fournisseurs', '/fournisseurs', 'building-2'),
            self::link('Types de dépense', '/type-depenses', 'list'),
        ];
    }

    /**
     * @return array{title: string, href: string, icon?: string}
     */
    private static function link(string $title, string $href, ?string $icon = null): array
    {
        $item = ['title' => $title, 'href' => $href];

        if ($icon !== null) {
            $item['icon'] = $icon;
        }

        return $item;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{title: string, icon?: string, items: list<array<string, mixed>>}
     */
    private static function section(string $title, ?string $icon, array $items): array
    {
        $section = [
            'title' => $title,
            'items' => self::compact($items),
        ];

        if ($icon !== null) {
            $section['icon'] = $icon;
        }

        return $section;
    }

    /**
     * @param  list<array<string, mixed>|null>  $items
     * @return list<array<string, mixed>>
     */
    private static function compact(array $items): array
    {
        return array_values(array_filter($items, fn ($item) => $item !== null));
    }

    private static function userIsInCommittee(int $userId): bool
    {
        if (! Schema::hasTable('comite_user')) {
            return false;
        }

        return DB::table('comite_user')->where('user_id', $userId)->exists();
    }
}
