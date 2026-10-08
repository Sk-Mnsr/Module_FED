# Manuel d’utilisation — Monétique (Coficarte)

**Application :** COFI-COMPTA  
**Public :** responsable monétique, chefs d’agence, chargés de clientèle, caissiers  
**Objectif :** expliquer comment suivre le stock de cartes Coficarte, les transférer, les vendre, les recharger et les encaisser.

---

## Comment lire ce manuel

- Les chemins de menu sont indiqués ainsi :  
  **Menu → Sous-menu → Écran**
- Les boutons et libellés à l’écran sont en **gras**.
- Les points importants sont signalés par **Attention**.
- Chaque mention **Capture à insérer** est un cadre vide : collez-y la capture d’écran indiquée.

---

## 1. À quoi sert le module ?

Le module **Monétique** suit le cycle de vie des cartes **Coficarte** :

1. Enregistrer les cartes reçues au siège (stock central).
2. Les envoyer vers une agence, puis vers un chargé de clientèle.
3. Les vendre ou enregistrer une recharge.
4. Faire encaisser l’opération à la caisse, avec un code de bordereau.

**Attention :** une vente ou une recharge n’est définitive qu’après confirmation par la caisse. Tant que le code n’est pas encaissé, l’opération reste **en attente**.

---

## 2. Qui fait quoi ?

L’accès au module vient de l’autorisation **Monétique**. Le menu change selon le rôle.

| Rôle | Qui est-ce ? | Que peut-il faire ? |
|------|--------------|---------------------|
| **Monétique** | Responsable au siège | Stock central, prix, lots, demandes des agences, transferts, campagnes, seuils, ventes et recharges, caisse |
| **Chef d’agence** (`ca`) | Responsable d’une agence | Demander des cartes au siège, réceptionner, alimenter les CC, retourner des cartes, suivre son agence |
| **Chargé de clientèle** (`cc`) | Vendeur en agence | Vendre et recharger les cartes qui lui sont affectées ; délester vers le chef d’agence |
| **Caissier** | Caisse | Encaisser une vente ou une recharge avec le code du bordereau |
| **IT / SuperAdmin** | Administration | Même vue que le siège sur l’ensemble du réseau |

**Règles essentielles :**

- Un **chef d’agence** ne saisit pas une vente ni une recharge. C’est le **chargé de clientèle** (ou le siège) qui le fait.
- Un chargé de clientèle ne voit, dans **En stock**, que les cartes **qui lui sont affectées**.
- Un chef d’agence voit les cartes de **son agence**. Le siège voit **tout le réseau**.

---

## 3. Où trouver le module ?

1. Se connecter à COFI-COMPTA.
2. Ouvrir le module **Monétique** depuis le portail (ou le menu latéral).

### Menu du siège (rôle Monétique)

```
Monétique
 ├── Pilotage
 │    ├── Tableau de bord
 │    ├── Campagnes
 │    └── Seuils & objectifs
 ├── Cartes
 │    ├── Ajouter
 │    ├── Modifier prix
 │    ├── Modifier lots
 │    ├── En stock
 │    └── Vendues
 ├── Transferts
 │    ├── Demandes d'agences
 │    ├── Nouveau transfert
 │    ├── En attente
 │    └── Historique
 ├── Ventes
 │    ├── Nouvelle vente
 │    └── Historique
 ├── Recharges
 │    ├── Nouvelle recharge
 │    └── Historique
 └── Caisse
      └── Encaissement
```

[[CAPTURE|Menu du siège (rôle Monétique)]]

### Menu du chef d’agence

```
Monétique
 ├── Pilotage
 │    ├── Vue agence
 │    └── Suivi ventes & recharges
 ├── Cartes
 │    ├── En stock
 │    └── Vendues
 ├── Transferts
 │    ├── Demandes au siège
 │    ├── Réception des cartes
 │    ├── Retour au siège
 │    ├── Approvisionnement CC
 │    └── Historique
 ├── Ventes
 │    └── Historique
 ├── Recharges
 │    └── Historique
 └── Caisse          ← seulement si le rôle Caissier est aussi attribué
      └── Encaissement
```

[[CAPTURE|Menu du chef d’agence]]

Si le chef d’agence a aussi le rôle **Chargé de clientèle**, il voit en plus **Nouvelle vente**, **Nouvelle recharge** et **Délester vers le chef d’agence**.

### Menu du chargé de clientèle

```
Monétique
 ├── Pilotage → Indicateurs
 ├── Cartes → En stock / Vendues
 ├── Transferts → Délester vers le chef d’agence
 ├── Ventes → Nouvelle vente / Historique
 └── Recharges → Nouvelle recharge / Historique
```

[[CAPTURE|Menu du chargé de clientèle]]

### Menu du caissier

```
Monétique
 └── Caisse → Encaissement
```

[[CAPTURE|Menu du caissier]]

(Le caissier voit aussi les autres entrées si un autre rôle monétique lui est attribué.)

---

## 4. Le parcours d’une carte

| Étape | Où | Résultat |
|-------|-----|----------|
| 1. Réception au siège | Cartes → **Ajouter** | Carte **en stock** au siège |
| 2. Demande d’agence | Transferts → **Demandes au siège** | Demande **en attente** |
| 3. Envoi | Transferts → **Nouveau transfert** | Cartes **en transfert** |
| 4. Réception agence | Transferts → **Réception des cartes** | Stock de l’agence |
| 5. Remise au CC | Transferts → **Approvisionnement CC** | Carte affectée au chargé de clientèle |
| 6. Vente | Ventes → **Nouvelle vente** | Carte **en attente d’encaissement** + code **V-…** |
| 7. Caisse | Caisse → **Encaissement** | Vente confirmée, carte **vendue** |

Une **recharge** suit les étapes 6 et 7 avec un code **R-…**, sans changer le propriétaire de la carte.

---

## 5. Les statuts

### Carte

| Statut | Signification |
|--------|---------------|
| **En stock** | Disponible (siège, agence ou CC selon l’affectation) |
| **En transfert** | Envoyée, pas encore réceptionnée |
| **En attente d’encaissement** | Vendue côté commercial, caisse pas encore confirmée |
| **Vendue** | Encaissement confirmé |

### Demande d’approvisionnement

| Statut affiché | Signification |
|----------------|---------------|
| **En attente** | L’agence a demandé des cartes, le siège n’a pas encore livré |
| **Transfert en attente de réception** | Un transfert a été créé, l’agence doit réceptionner |
| **Livraison partielle — suite possible** | Une partie a été livrée, le siège peut compléter |
| **Satisfaite / clôturée** | La demande est soldée |
| **Refusée** | Le siège a refusé (un motif est indiqué) |
| **Annulée par l’agence** | Le chef d’agence a annulé sa demande |

### Transfert

| Statut | Signification |
|--------|---------------|
| **En attente** | Le destinataire doit valider la réception |
| **Validé** | Réception confirmée |
| **Rejeté** | Réception refusée |
| **Annulé** | Transfert annulé avant réception |

### Vente et recharge

| Statut | Signification |
|--------|---------------|
| **En attente caisse** | Saisie faite, code généré, encaissement pas encore confirmé |
| **Encaissé** | La caisse a confirmé et joint le bordereau |

---

## 6. Enregistrer des cartes au siège

**Chemin :** Monétique → Cartes → **Ajouter**  
**Qui :** rôle **Monétique**

### Étapes

1. Choisir le mode :
   - **Lot** : plusieurs cartes d’affilée
   - **Une carte** : une seule carte
2. Renseigner :
   - quantité et numéro de la **première carte** (mode lot), ou le **numéro de carte**
   - **Numéro de lot** (mode lot)
   - **Référence facture** et le fichier **facture** (obligatoires)
   - bon de livraison (si vous l’avez)
   - **Prix de vente** et **prix d’achat**
   - **Date de livraison** et **date d’expiration**
3. Cliquer sur **Ajouter**.

→ Les cartes apparaissent dans **En stock**, au siège.

**Attention :** la facture et les deux prix sont obligatoires. Sans eux, l’enregistrement est refusé.

[[CAPTURE|Écran Ajouter des cartes (mode Lot)]]

[[CAPTURE|Écran Ajouter des cartes (mode Une carte)]]

---

## 7. Corriger un prix ou un lot

**Chemin :** Monétique → Cartes → **Modifier prix** ou **Modifier lots**  
**Qui :** rôle **Monétique**

- **Modifier prix** : met à jour le prix de vente (et d’achat si l’écran le propose) sur une sélection de cartes encore en stock.
- **Modifier lots** : attribue ou change le **numéro de lot**, en filtrant éventuellement par **référence facture**.

Les cartes déjà **vendues** ne se corrigent pas depuis ces écrans.

[[CAPTURE|Écran Modifier prix]]

[[CAPTURE|Écran Modifier lots]]

---

## 8. Consulter le stock et l’historique d’une carte

**Chemin :** Monétique → Cartes → **En stock** ou **Vendues**

- **En stock** : cartes disponibles dans votre périmètre (réseau, agence, ou vos cartes si vous êtes CC).
- **Vendues** : cartes dont la vente a été encaissée.
- Depuis une carte, **Mouvements** retrace les transferts, la vente et les opérations liées.

La recherche accepte le numéro de carte, le lot ou la facture selon l’écran.

[[CAPTURE|Liste En stock]]

[[CAPTURE|Liste Vendues]]

[[CAPTURE|Mouvements d’une carte]]

---

## 9. Demander des cartes (chef d’agence)

**Chemin :** Monétique → Transferts → **Demandes au siège**

### Étapes

1. Saisir la **Quantité souhaitée**.
2. Ajouter un **Commentaire** (contexte, urgence, contact).
3. Envoyer la demande.

→ Elle apparaît chez le siège dans **Demandes d'agences**, au statut **En attente**.

Vous pouvez **annuler** une demande encore en attente. Une demande déjà transférée ou refusée ne s’annule plus de cette façon.

[[CAPTURE|Demander des cartes au siège]]

---

## 10. Traiter une demande et transférer (siège)

**Chemin :** Monétique → Transferts → **Demandes d'agences**

Pour chaque demande :

- **Créer le transfert** si le bouton est proposé : vous arrivez sur **Transférer des cartes**, avec l’agence déjà ciblée.
- **Refuser** : un **motif** est obligatoire. L’agence voit le statut **Refusée**.

[[CAPTURE|Demandes des agences (siège)]]

### Transfert sans demande

**Chemin :** Monétique → Transferts → **Nouveau transfert**

1. Choisir la **référence facture**, puis le **lot**.
2. Cocher les cartes à envoyer (plusieurs factures peuvent être cumulées).
3. Choisir le **chef d’agence** destinataire.
4. Ajouter un commentaire si besoin.
5. Valider l’envoi.

→ Un **bon de transfert** est généré. Les cartes passent **en transfert** jusqu’à la réception.

Depuis **En attente** ou **Historique**, le **bon PDF** peut être ouvert ou téléchargé.

**Attention :** tant que l’agence n’a pas validé la réception, les cartes ne sont pas dans son stock vendable.

[[CAPTURE|Nouveau transfert — sélection des cartes]]

[[CAPTURE|Bon de transfert (PDF)]]

---

## 11. Réceptionner les cartes (agence)

**Chemin :** Monétique → Transferts → **Réception des cartes**

1. Ouvrir le transfert **en attente** qui vous est destiné.
2. Vérifier le nombre de cartes et le bon.
3. **Valider la réception** si le colis est conforme, ou **rejeter** s’il ne l’est pas.

→ Après validation, les cartes sont **en stock** dans l’agence (pas encore chez un CC).

L’**Historique** des transferts permet de revoir les bons déjà traités.

[[CAPTURE|Réception des cartes]]

[[CAPTURE|Historique des transferts]]

---

## 12. Alimenter un chargé de clientèle

**Chemin :** Monétique → Transferts → **Approvisionnement CC**  
**Qui :** chef d’agence

1. Choisir le **Chargé de clientèle destinataire**.
2. Sélectionner les cartes du stock agence à lui remettre.
3. Valider.

→ Ces cartes apparaissent dans **En stock** du CC, et seulement dans le sien.

[[CAPTURE|Approvisionnement des chargés de clientèle]]

### Rendre des cartes au chef d’agence

**Chemin (CC) :** Monétique → Transferts → **Délester vers le chef d’agence**

Le CC sélectionne des cartes qui lui sont affectées et les renvoie au stock de l’agence. Il ne peut pas les renvoyer directement au siège.

[[CAPTURE|Délester vers le chef d’agence]]

---

## 13. Retourner des cartes au siège

**Chemin :** Monétique → Transferts → **Retour au siège**  
**Qui :** chef d’agence

À utiliser pour des cartes encore **en stock agence** (non vendues) que l’agence ne garde pas.

1. Sélectionner les cartes encore en stock agence, non affectées à un CC.
2. Valider le retour.

→ Elles reviennent tout de suite au **stock central** du siège. Ce n’est pas un transfert à réceptionner.

[[CAPTURE|Retour de cartes au siège]]

---

## 14. Vendre une carte

**Chemin :** Monétique → Ventes → **Nouvelle vente**  
**Qui :** chargé de clientèle, ou siège

### Étapes

1. Dans **Carte vendue**, choisir une carte **en stock** dans votre périmètre.
2. Vérifier le prix, l’expiration et les **4 derniers chiffres** avec la carte physique.
3. Renseigner la **Date de la vente**.
4. Compléter **Acheteur & contact** :
   - type : **Particulier** ou **Entreprise**
   - **Titulaire de la carte**, téléphone, e-mail, adresse
5. Compléter **Compte client** :
   - **In Pack** : le **numéro de compte** est demandé
   - **Hors Pack** : pas de numéro de compte
6. Renseigner l’opération :
   - **Montant 1re recharge** (peut être 0)
   - **Apporteur d’affaires** (obligatoire si l’écran l’indique par une étoile)
   - **Campagne** si une campagne est en cours (facultatif)
   - pièces KYC et **fiche d’enrôlement** si elles sont demandées
7. Enregistrer.

→ La vente est **en attente caisse**. Un code du type **V-…** est produit pour le bordereau. Remettez ce code au caissier.

**Attention :** la carte n’est pas **vendue** tant que la caisse n’a pas confirmé. Elle reste **en attente d’encaissement** et n’est plus proposée à une autre vente.

Le bouton **Historique des ventes** permet de retrouver le code et le statut.

[[CAPTURE|Nouvelle vente — carte et acheteur]]

[[CAPTURE|Historique des ventes et code V-]]

---

## 15. Enregistrer une recharge

**Chemin :** Monétique → Recharges → **Nouvelle recharge**  
**Qui :** chargé de clientèle, ou siège

Une recharge concerne une carte **déjà vendue**.

### Étapes

1. Saisir le **numéro de carte**.
2. Renseigner le **titulaire** (l’e-mail est facultatif).
3. Saisir le **montant** de la recharge et les **honoraires** éventuels.
4. Rattacher une **campagne** si besoin.
5. Ajouter une **remarque** si utile.
6. Enregistrer.

→ La recharge est **en attente caisse**. Un code **R-…** est produit. Le total à encaisser (montant + honoraires) est affiché à l’écran.

**Attention :** toute carte vendue de votre agence peut être rechargée par son numéro, même si elle n’est plus dans votre stock personnel.

Suivez le statut dans **Historique**.

[[CAPTURE|Nouvelle recharge]]

[[CAPTURE|Historique des recharges et code R-]]

---

## 16. Encaisser à la caisse

**Chemin :** Monétique → Caisse → **Encaissement**  
**Qui :** caissier, monétique ou IT

L’écran a deux onglets : **Encaissement** et **Historique**.

### Encaisser

1. Rester sur **Encaissement**.
2. Saisir le **code** remis par le commercial :
   - **V-…** pour une vente
   - **R-…** pour une recharge
3. Cliquer sur **Rechercher l’opération** (ou Entrée).
4. Vérifier le détail (carte, client, montant).
5. Joindre le **bordereau de caisse**, puis confirmer l’encaissement.

→ La vente ou la recharge passe **encaissée**. Pour une vente, la carte devient **vendue**.

Si le message « Aucune opération en attente… » s’affiche : le code est incorrect, déjà encaissé, ou hors de votre périmètre.

[[CAPTURE|Caisse — saisie du code d’encaissement]]

[[CAPTURE|Caisse — détail de l’opération et bordereau]]

### Historique caisse

L’onglet **Historique** liste les ventes et recharges déjà encaissées. Le bordereau peut être rouvert depuis la ligne.

[[CAPTURE|Caisse — historique des encaissements]]

---

## 17. Pilotage, campagnes et seuils

**Qui :** surtout le siège. L’agence a une **Vue agence** et un **Suivi ventes & recharges**.

### Tableau de bord

**Chemin :** Monétique → Pilotage → **Tableau de bord** (siège) ou **Vue agence** / **Indicateurs**

Il compare, sur le mois en cours, les **ventes** et les **recharges encaissées** aux objectifs. Un export est disponible depuis l’écran de pilotage.

[[CAPTURE|Tableau de bord / vue agence]]

### Campagnes

**Chemin :** Monétique → Pilotage → **Campagnes**

1. Donner un **nom** (exemple : Campagne Ramadan).
2. Choisir une **agence**, ou laisser vide pour **toutes**.
3. Fixer l’**objectif ventes** et l’**objectif recharges (FCFA)**.
4. Indiquer **début** et **fin**.

Une campagne active peut être choisie lors d’une vente ou d’une recharge.

[[CAPTURE|Campagnes commerciales]]

### Seuils et objectifs

**Chemin :** Monétique → Pilotage → **Seuils & objectifs**

- **Seuil minimum cartes au siège** : alerte lorsque le stock central passe sous ce nombre.
- **Objectifs réseau** : cibles du mois pour tout le réseau (ventes et recharges encaissées).
- **Objectifs par agence** : mêmes indicateurs, limités à l’agence.

**Attention :** une valeur **0** désactive le seuil ou l’objectif. Les objectifs se lisent sur le **pilotage**, à partir des opérations **encaissées**, pas des saisies encore en attente caisse.

[[CAPTURE|Seuils d’alerte et objectifs]]

---

## 18. Que faire selon la situation ?

| Besoin | Chemin |
|--------|--------|
| Mettre des cartes neuves en stock | Cartes → **Ajouter** |
| Voir mes cartes disponibles | Cartes → **En stock** |
| Demander un réassort | Transferts → **Demandes au siège** |
| Envoyer des cartes à une agence | Transferts → **Nouveau transfert** |
| Réceptionner un envoi | Transferts → **Réception des cartes** |
| Donner des cartes à un CC | Transferts → **Approvisionnement CC** |
| Vendre | Ventes → **Nouvelle vente** |
| Recharger | Recharges → **Nouvelle recharge** |
| Encaisser | Caisse → **Encaissement** |
| Vérifier les objectifs du mois | Pilotage → **Tableau de bord** |

---

## 19. Points d’attention

- **Quatre yeux commercial / caisse.** Celui qui saisit la vente ou la recharge n’encaisse pas à la place de la caisse : il transmet le code **V-** ou **R-**.
- **Périmètre.** Un CC ne vend que ses cartes. Une recherche ou un code hors agence ne s’affiche pas.
- **Demande refusée.** Lisez le motif côté agence avant de refaire une demande.
- **Carte en transfert.** Elle n’est ni vendable ni rechargeable tant que la réception n’est pas validée.
- **Première recharge à la vente.** Le montant saisi sur la vente fait partie de l’opération à encaisser avec le code **V-**. Une recharge ultérieure est une opération séparée, code **R-**.
