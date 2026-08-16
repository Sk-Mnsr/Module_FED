# Gestion des accès — Modules × Rôles

## Principe

Chaque module (OD, Monétique, FED, etc.) est traité comme une **application**.  
Un utilisateur n’a **pas un profil métier global** : il a **0 ou 1 rôle par module**.

```
Utilisateur
    ├── Identité (nom, email, IDFLEX, agence, N+1…)
    └── Accès
        ├── Module OD            → rôle OPS / Finance…
        ├── Module Budget        → autorisation seule (on/off)
        ├── Module Réconciliation → autorisation seule (on/off)
        ├── Module Référentiels  → autorisation seule (on/off)
        └── Module Monétique     → aucun / CA / CC…
```

**Source de vérité :** table `roles` + pivot `role_module` + `user_role`.  
**Pas** `users.profile = monetique` pour ouvrir un module.

---

## `users.profile` (legacy réduit)

| Valeur | Signification |
|--------|----------------|
| `admin` | Bypass administration (équivalent rôle IT) |
| `other` | Utilisateur métier normal |

- À la création / édition / import, `profile` est **recalculé** depuis les rôles (`User::syncAccessProfileFromRoles()`).
- Un rôle monétique ne met plus `profile = monetique`.
- L’accès Monétique vient uniquement du module `monetique` dans la matrice.

---

## Règles opérationnelles

1. **Créer un utilisateur** → identité + **Accès & rôle** (une liste par module).
2. **Un seul rôle par module** (validation côté `UserController`).
3. **Rôle IT** → accès à tous les modules + Administration sur le portail (bypass).
4. **Modules « autorisation seule »** (`budget`, `reconciliation`, `config`) : case à cocher.  
   **Budget** : après accès, droits granulaires — Consultation / Ajouter / Modifier / Supprimer / Importer-Exporter (`user_module_abilities`).
5. **Rôle multi-modules** (hors modules « access only ») : défini dans `role_module`.
6. **Middleware** `module:…` + `ModuleAccess::userCanAccess()` / `ModuleAbilities::userCan()` pour sécuriser les routes.

---

## Matrice modules (catalogue)

| Clé | Libellé |
|-----|---------|
| `fed` | FED |
| `budget` | Budget (**autorisation seule**) |
| `stock` | Gestion de stock |
| `ecritures` | Écritures comptables |
| `monetique` | Monétique |
| `od` | Opérations diverses |
| `reconciliation` | Réconciliation Flexcube (**autorisation seule**) |
| `config` | **Référentiels** (**autorisation seule**, clé `config`) |

Contenu du module Référentiels : typologies, catégories, banques, fournisseurs, types de dépense.  
Administration (IT, portail) : utilisateurs, rôles, départements, agences, articles, familles, apporteurs, paramètres.  

Les rôles autorisés par module sont dans `role_module` (seed / écran Rôles).  
Fallback legacy : `ModuleAccess` (tableau `legacyRoleSlugsByModule`).  
Modules « access only » : `ModuleAccess::ACCESS_ONLY_MODULES`.

---

## Rôles typiques

| Module | Exemples de rôles |
|--------|-------------------|
| Référentiels (`config`) | `referentiels` (on/off) |
| Budget | `budget` (on/off) + droits CRUD granulaires |
| Réconciliation | `reconciliation` (on/off) |
| OD | `ops`, `finance` |
| Monétique | `monetique`, `monetique_ops`, `ca`, `cc`, `caissier` |
| FED | `demandeur`, `n_plus_1`, `responsable_achats`, `daf`, `dga`… |
| Stock | `responsable_stock`, `responsable_achats` |
| Administration | `it` (SuperAdmin) |

---

## Fichiers clés

| Fichier | Rôle |
|---------|------|
| `app/Support/ModuleAccess.php` | Modules, accès, matrice, admin |
| `app/Support/RoleAccessProfile.php` | Calcul `users.profile` = admin\|other |
| `app/Support/AppNavigation.php` | Menus filtrés par module + rôles |
| `app/Support/AppPortal.php` | Cartes du portail |
| `app/Http/Middleware/CheckModule.php` | Garde-fou routes |
| `resources/js/components/RoleModuleSelect.vue` | UI Accès & rôle |

---

## Ce qu’il ne faut plus faire

- Donner l’accès Monétique via `users.profile = monetique`
- Dupliquer la logique d’accès seulement dans la sidebar
- Créer un “super profil comptable” hors rôles / matrice
