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
        ├── Administration       → autorisation seule (on/off)
        └── Module Monétique     → aucun / CA / CC…
```

**Source de vérité :** table `roles` + pivot `role_module` + `user_role`.  
**Pas** `users.profile = monetique` pour ouvrir un module.

---

## `users.profile` (legacy réduit)

| Valeur | Signification |
|--------|----------------|
| `admin` | Bypass SuperAdmin (équivalent rôle IT) |
| `other` | Utilisateur métier / administrateur système |

- À la création / édition / import, `profile` est **recalculé** depuis les rôles (`User::syncAccessProfileFromRoles()`).
- Un rôle monétique ne met plus `profile = monetique`.
- L’accès Monétique vient uniquement du module `monetique` dans la matrice.

---

## SuperAdmin vs Administrateur

| | SuperAdmin (`it`) | Administrateur (`administrateur`) |
|--|-------------------|-----------------------------------|
| Profil | `access_profile = admin` | `access_profile = other` |
| Modules métier | **Tous** (bypass) | Aucun automatique — à cocher séparément |
| Administration | Oui | Oui (utilisateurs, rôles, départements, agences, articles, familles, apporteurs, paramètres) |
| Portail | Toutes les apps + Administration | Section Administration (+ apps si d’autres rôles) |

---

## Règles opérationnelles

1. **Créer un utilisateur** → identité + **Accès & rôle** (une liste par module).
2. **Un seul rôle par module** (validation côté `UserController`).
3. **Rôle SuperAdmin (`it`)** → accès à tous les modules + Administration (bypass).
4. **Rôle Administrateur** → Administration système uniquement (sans bypass métier).
5. **Modules « autorisation seule »** (`budget`, `reconciliation`, `config`, `administration`) : case à cocher.  
   **Budget** : après accès, droits granulaires — Consultation / Ajouter / Modifier / Supprimer / Importer-Exporter (`user_module_abilities`).
6. **Rôle multi-modules** (hors modules « access only ») : défini dans `role_module`.
7. **Middleware** `module:…` + `ModuleAccess::userCanAccess()` / `canAdministerSystem()` / `ModuleAbilities::userCan()` pour sécuriser les routes.

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
| `administration` | **Administration** (**autorisation seule**, hors cartes portail) |

Contenu du module Référentiels : typologies, catégories, banques, fournisseurs, types de dépense.  
Administration (portail) : utilisateurs, rôles, départements, agences, articles, familles, apporteurs, paramètres.  

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
| Administration | `administrateur` (système) / `it` (SuperAdmin) |

---

## Fichiers clés

| Fichier | Rôle |
|---------|------|
| `app/Support/ModuleAccess.php` | Modules, accès, matrice, SuperAdmin / Administration |
| `app/Support/RoleAccessProfile.php` | Calcul `users.profile` = admin\|other |
| `app/Support/AppNavigation.php` | Menus filtrés par module + rôles |
| `app/Support/AppPortal.php` | Cartes du portail + liens Administration |
| `app/Http/Middleware/CheckModule.php` | Garde-fou routes |
| `resources/js/components/RoleModuleSelect.vue` | UI Accès & rôle |

---

## Ce qu’il ne faut plus faire

- Donner l’accès Monétique via `users.profile = monetique`
- Dupliquer la logique d’accès seulement dans la sidebar
- Créer un “super profil comptable” hors rôles / matrice
- Confondre **Administrateur** (gestion système) et **SuperAdmin / IT** (bypass)
