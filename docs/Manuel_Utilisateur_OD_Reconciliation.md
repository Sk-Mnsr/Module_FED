# Manuel d’utilisation — Opérations diverses & Réconciliation Flexcube

**Application :** COFI-COMPTA  
**Public :** utilisateurs métier (agents Operations / Finance, validateurs, administrateurs)  
**Objectif :** expliquer clairement comment utiliser les modules au quotidien.

---

## Comment lire ce manuel

- Les chemins de menu sont indiqués ainsi :  
  **Menu → Sous-menu → Écran**
- Les boutons et libellés à l’écran sont en **gras**.
- Les points importants sont signalés par **Attention**.

---

# Partie 1 — Opérations diverses (OD)

## 1. À quoi sert le module ?

Le module **Opérations diverses** permet de :

1. Préparer une intégration comptable (fichier CSV **ou** saisie manuelle).
2. L’envoyer vers **Flexcube**.
3. La faire **valider** par un autre agent (principe des **4 yeux** : maker / checker).
4. **Archiver** la pièce comptable et les justificatifs.

---

## 2. Qui fait quoi ?

| Rôle | Qui est-ce ? | Que peut-il faire ? |
|------|--------------|---------------------|
| **Maker** | L’agent qui crée l’intégration | Créer, modifier, intégrer, désigner un checker |
| **Checker** | Un **autre** agent du **même pôle** (Operations ou Finance) | Valider et archiver, ou rejeter |
| **Contrôleur** | Profil dédié au contrôle documentaire (distinct du Contrôle de gestion FED) | Après archivage : contrôler OK, ou **signaler une erreur** (notif maker → pièce de correction) |
| **SuperAdmin** | Administrateur | Voir toute la file, accéder à la **Corbeille**, restaurer, détruire |

**Règle essentielle :**  
un agent **Operations** ne peut désigner comme checker qu’un autre agent **Operations**.  
Idem pour **Finance**.

---

## 3. Où trouver le module ?

1. Se connecter à COFI-COMPTA.
2. Ouvrir le module **Opérations diverses** depuis le portail (ou le menu latéral).

Menu latéral :

```
Opérations diverses
 ├── Intégration
 │    ├── Automatique
 │    ├── Manuelle
 │    └── Mes brouillons
 ├── En attente
 ├── Corbeille          ← SuperAdmin uniquement
 └── Archivage
```

---

## 4. Les statuts d’une intégration

| Statut affiché | Signification |
|----------------|---------------|
| **Brouillon** | En cours de préparation, pas encore envoyée |
| **Attente de validation** | Transmise à Flexcube, en attente du checker |
| **Archivé** | Validée par le checker ; pièce disponible dans l’archivage |
| **Archivé · Contrôlé** | Le Contrôleur a validé justificatifs + pièce comptable |
| **Archivé · Contrôlé · Correction** | Contrôlée avec erreurs signalées ; la pièce d’origine reste archivée ; le maker crée une **nouvelle** pièce de correction |

---

## 5. Créer une intégration automatique (CSV)

**Chemin :** Opérations diverses → Intégration → **Automatique**

### Étapes

1. Renseigner les **Identifiants** :
   - **Date valeur** (obligatoire)
   - **Nom du classeur** (obligatoire)
   - **Numéro batch** (optionnel)  
     Si vide, un numéro provisoire `EN_ATTENTE` peut s’afficher jusqu’à l’attribution Flexcube.
2. Déposer le **fichier CSV** (ou TXT).  
   Utiliser le **Modèle CSV** si besoin.
3. Ajouter au moins **une pièce justificative** (description + fichier) via **Ajouter**.
4. Cliquer sur **Enregistrer**.

→ L’intégration est créée en **brouillon** et vous arrivez sur le **Résumé**.

---

## 6. Créer une intégration manuelle (saisie ligne à ligne)

**Chemin :** Opérations diverses → Intégration → **Manuelle**

### Étapes

1. Renseigner le **nom du classeur** (et le batch si vous le connaissez).
2. Dans **Lignes des OD**, cliquer sur **Ajouter une ligne**.
3. Pour chaque ligne, saisir notamment :
   - Agence
   - N° de compte
   - Sens : **D — Débit** ou **C — Crédit**
   - Montant
   - Code opération / libellé (selon les champs demandés)
4. Joindre au moins **une pièce justificative**.
5. Cliquer sur **Enregistrer**.

→ Vous arrivez sur le **Résumé**.

**Astuce :** depuis **Mes brouillons**, le bouton **Nouvelle intégration** propose aussi Automatique ou Manuelle.

---

## 7. Gérer mes brouillons

**Chemin :** Opérations diverses → Intégration → **Mes brouillons**

Cet écran liste **uniquement vos** brouillons.

Vous pouvez :

- Rechercher (nom de classeur, n° batch, etc.)
- Ouvrir le **Résumé**
- **Intégrer** (si le brouillon est prêt)
- **Supprimer** (l’élément part en corbeille)

---

## 8. Lire le résumé avant d’intégrer

**Écran :** Résumé de l’intégration

Vérifiez avant toute action :

- Numéro de batch et date valeur
- Nom du classeur
- Maker / checker (si déjà désigné)
- Totaux **Débit** / **Crédit** et la **Différence** (doit être **équilibré**)
- Aperçu des lignes
- Pièces justificatives (voir / télécharger)

### Actions selon le statut

| Si le statut est… | Vous pouvez… |
|-------------------|--------------|
| **Brouillon** (et vous êtes le créateur) | **Modifier**, **Supprimer**, **Intégrer (maker)** |
| **Attente de validation** (et vous êtes le checker) | **Valider**, ou rejeter depuis la file |
| **Attente** (mais vous n’êtes pas le checker) | Consulter uniquement ; le validateur attendu est indiqué |
| **Archivé** | Consulter et télécharger |

---

## 9. Intégrer (rôle maker)

**Objectif :** envoyer l’opération à Flexcube et désigner le checker.

### Étapes

1. Ouvrir le **Résumé** d’un brouillon (ou cliquer **Intégrer** depuis **Mes brouillons**).
2. Cliquer sur **Intégrer (maker)**.
3. Dans la fenêtre, **choisir le validateur (checker)**.
4. Confirmer.

### Ce qui se passe ensuite

- L’intégration part vers Flexcube.
- Le statut passe en **Attente de validation**.
- Le numéro de **batch** peut être mis à jour.
- Une pièce comptable PDF est générée.
- Le checker est notifié (e-mail).
- Une confirmation s’affiche (bannière et/ou popup) avec le **numéro de batch**.

### Attention

- Seul le **créateur** peut intégrer.
- Le checker doit être un **autre** agent du **même pôle**.
- Si le service Flexcube est indisponible, l’intégration est refusée (message d’erreur en popup).
- Exemple de message possible : *« Vous êtes déjà connecté ailleurs… »* → se déconnecter de l’autre session Flexcube puis réessayer.

---

## 10. Valider ou rejeter (rôle checker)

**Chemin :** Opérations diverses → **En attente**

**Écran :** File d’attente checker

- En tant qu’agent : vous ne voyez que les dossiers **où vous êtes le checker désigné**.
- En tant que SuperAdmin / Admin : vous voyez **toute** la file.

### Actions sur une ligne

| Icône / action | Effet |
|----------------|-------|
| **Résumé** (œil) | Ouvrir le détail |
| **Valider** (vert) | Archiver définitivement côté application |
| **Rejeter** (ambre) | Renvoyer en brouillon au maker (motif optionnel) |
| **Supprimer** (rouge) | Mettre en corbeille |

### Valider

1. Cliquer sur **Valider**.
2. Confirmer : *« Voulez-vous vraiment valider … ? »*
3. L’intégration devient **Archivée**.
4. La pièce finalisée est disponible dans **Archivage**.

### Rejeter

1. Cliquer sur **Rejeter**.
2. Saisir un **motif** (optionnel mais recommandé).
3. Confirmer.
4. Le maker retrouve un **brouillon** à corriger, puis peut réintégrer.

### Attention

Seul le **checker désigné** peut valider ou rejeter.

---

## 11. Corbeille (SuperAdmin)

**Chemin :** Opérations diverses → **Corbeille**

- Contient les intégrations **supprimées** (fichiers conservés).
- Action **Restaurer** : le dossier réapparaît selon son statut d’origine (brouillon, attente ou archivage).
- Action **Supprimer définitivement** : efface les fichiers et l’enregistrement.

### Qui peut supprimer ?

| Situation | Qui peut supprimer |
|-----------|--------------------|
| Brouillon | Le créateur (ou SuperAdmin) |
| En attente | Le checker, le maker, ou SuperAdmin |
| Archivé | **SuperAdmin uniquement** (mise en corbeille, puis suppression définitive possible) |

---

## 12. Archivage

**Chemin :** Opérations diverses → **Archivage**

Contient les intégrations **validées**.

Organisation type :

```
Dossiers Comptables
 └── Finance ou Operations
      └── Année
           └── Mois
                └── Journée
                     └── Agent
                          └── Pièce (nom du classeur)
```

Vous pouvez :

- Rechercher (classeur, batch, agent…)
- Ouvrir une pièce
- Télécharger le PDF et les justificatifs
- **Contrôleur** : **Contrôler la pièce** (OK) ou **Signaler une erreur** (notif maker)
- **Maker** : bouton **J’ai créé la correction** → retire la notif et ouvre une **nouvelle intégration** (indépendante, non liée)

**Visibilité :** chaque pôle voit principalement ses pièces ; Contrôleur et SuperAdmin voient tout.

---

## 13. Schéma du cycle OD

```
Automatique  ou  Manuelle
          │
          ▼  Enregistrer
     Brouillon
          │
          ▼  Intégrer (maker) + choisir checker
  Attente de validation
          │
     ┌────┴────┐
     ▼         ▼
  Valider    Rejeter
     │         │
     ▼         ▼
 Archivage   Brouillon (corriger puis renvoyer)
     │
     ▼  Contrôleur
 Archivé · Contrôlé
```

---

# Partie 2 — Réconciliation Flexcube

## 14. À quoi sert le module ?

Le module **Réconciliation Flexcube** permet de :

1. Comparer les mouvements **Flexcube** avec les fichiers d’un **partenaire** (ex. Wave).
2. Identifier ce qui est réconcilié et ce qui présente un écart.
3. Télécharger un **Excel** de résultats.
4. Conserver un **historique** des traitements.

---

## 15. Accès et menu

**Prérequis :** avoir le droit d’accès au module **Réconciliation**.

Menu latéral :

```
Réconciliation Flexcube
 ├── Reconciliation
 ├── Historique
 └── Partenaires
```

---

## 16. Gérer les partenaires

**Chemin :** Réconciliation Flexcube → **Partenaires**

Avant de réconcilier, le partenaire doit exister dans le référentiel.

### Créer un partenaire

1. Cliquer sur **Nouveau partenaire** (ou équivalent).
2. Saisir :
   - **Identifiant** (obligatoire)
   - **Nom** (obligatoire)
   - **Icône** (optionnelle)
3. **Enregistrer**.

### Modifier / supprimer

- **Modifier** : corriger les informations.
- **Supprimer** : confirmation demandée.

---

## 17. Lancer une réconciliation

**Chemin :** Réconciliation Flexcube → **Reconciliation**

### Étape A — Choisir le partenaire

1. Rechercher le partenaire.
2. Cliquer sur sa fiche.
3. Si besoin, utiliser **Gérer** pour aller au référentiel partenaires.

### Étape B — Préparer la réconciliation

Sur la fiche partenaire, section **Préparer la réconciliation** :

1. **Fichiers**  
   Déposer le(s) fichier(s) partenaire (Excel / CSV / TXT).  
   Respecter les colonnes attendues selon le mode (opération par opération, ou par agence).

2. **Période**  
   Indiquer **Date début** et **Date fin**.

3. **Lancer**  
   Boutons disponibles :

| Bouton | Rôle |
|--------|------|
| **Charger** | Envoie les fichiers et la période au service |
| **Lancer la réconciliation** | Exécute le rapprochement |
| **Réinitialiser** | Remet à zéro la session en cours |
| **Télécharger l’Excel** | Récupère le fichier de résultats |

### Attention

- Vérifier que le bandeau indique **Service disponible**.
- Si le service est **indisponible**, patienter ou contacter le support.
- Toujours **Charger** avant (ou selon la procédure affichée), puis **Lancer la réconciliation**.

---

## 18. Lire les résultats

Après un lancement réussi, des onglets permettent d’analyser :

| Onglet (selon le mode) | Contenu |
|------------------------|---------|
| **Excel** / **Flex** | Données chargées |
| **Résumé** | Indicateurs, cartes de statuts, filtres |
| **Réconciliation** | Détail des lignes / agences |
| **Graphiques** | Répartition visuelle par statut |

### Exemples de statuts

- Réconcilié
- Réconcilié avec tolérance
- Réconcilié — écart de délai
- Écarts / non réconciliés (montants, opérations isolées, etc.)

Utilisez le **filtre par statut** dans le résumé pour cibler les anomalies.

Lien **Changer de partenaire** : revenir au choix initial.

---

## 19. Historique

**Chemin :** Réconciliation Flexcube → **Historique**

**Écran :** Historique des réconciliations

Chaque lancement (succès ou échec) laisse une trace :

- Partenaire
- Période
- Mode
- Taux
- Statut
- Utilisateur
- Date

Actions utiles :

- Filtrer / rechercher
- **Actualiser**
- **Télécharger l’Excel** d’un run passé

---

## 20. Bonnes pratiques Réconciliation

1. Créer / vérifier le **partenaire** avant le run.
2. Contrôler le format du fichier (colonnes, séparateurs, dates).
3. Choisir une **période** cohérente avec le fichier.
4. Après le run, commencer par l’onglet **Résumé**.
5. Exporter l’Excel et le conserver si besoin de preuve / audit.
6. En cas d’échec, consulter l’**Historique** et le message d’erreur, puis **Réinitialiser** avant un nouvel essai.

---

# Partie 3 — Questions fréquentes

### Je ne vois pas le menu Opérations diverses / Réconciliation
→ Votre compte n’a probablement pas l’accès module. Contacter un administrateur.

### Je ne peux pas choisir mon checker
→ Le checker doit être un **autre** agent du **même pôle** (Operations ou Finance).

### L’intégration est refusée (popup rouge)
→ Lire le message (ex. session Flexcube déjà ouverte ailleurs). Corriger puis réessayer. Les **Détails techniques** aident le support.

### J’ai validé par erreur
→ Une validation archive l’intégration. Un **SuperAdmin** peut la **supprimer** depuis **Archivage** (mise en corbeille), puis éventuellement la supprimer définitivement depuis la **Corbeille**.

### J’ai supprimé un brouillon
→ Un SuperAdmin peut le **restaurer** depuis **Corbeille**.

### La réconciliation ne démarre pas
→ Vérifier **Service disponible**, le partenaire, les fichiers et la période ; utiliser **Réinitialiser** puis recommencer.

---

# Récapitulatif rapide

| Besoin | Où aller |
|--------|----------|
| Créer une OD depuis un CSV | OD → Intégration → **Automatique** |
| Saisir une OD à la main | OD → Intégration → **Manuelle** |
| Retrouver mes brouillons | OD → Intégration → **Mes brouillons** |
| Valider / rejeter | OD → **En attente** |
| Restaurer une suppression | OD → **Corbeille** (SuperAdmin) |
| Consulter les pièces validées | OD → **Archivage** |
| Supprimer une pièce archivée | OD → **Archivage** (SuperAdmin) → Corbeille |
| Créer un partenaire | Réconciliation → **Partenaires** |
| Lancer un rapprochement | Réconciliation → **Reconciliation** |
| Revoir un ancien run | Réconciliation → **Historique** |

---

*Document destiné aux utilisateurs métier de COFI-COMPTA — modules Opérations diverses et Réconciliation Flexcube.*
