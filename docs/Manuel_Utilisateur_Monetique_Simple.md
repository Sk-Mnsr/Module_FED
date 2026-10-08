# Monétique — guide simple

**Application :** COFI-COMPTA  
**Pour qui :** chargé de clientèle, caissier, chef d’agence, monétique siège

Ce guide dit seulement **quoi faire**, dans l’ordre.  
Les cadres **Capture à insérer** sont vides : collez-y la capture de l’écran.

**À retenir :** une vente ou une recharge n’est terminée que lorsque la **caisse** a encaissé le code.

---

## 1. Chargé de clientèle — vendre une carte

**Menu :** Monétique → Ventes → **Nouvelle vente**

1. Choisir une carte **en stock** (celles qui vous sont affectées).
2. Vérifier le prix et les **4 derniers chiffres** avec la carte physique.
3. Remplir le client : nom, téléphone, type (**Particulier** ou **Entreprise**).
4. Choisir le compte : **In Pack** (avec numéro de compte) ou **Hors Pack**.
5. Indiquer le montant de la **1re recharge** s’il y en a une, puis enregistrer.
6. Noter le code **V-…** et le remettre au caissier.

La carte reste **en attente** tant que la caisse n’a pas confirmé.

[[CAPTURE|Nouvelle vente]]

[[CAPTURE|Code V- dans l’historique des ventes]]

---

## 2. Chargé de clientèle — recharger une carte

**Menu :** Monétique → Recharges → **Nouvelle recharge**

1. Saisir le **numéro de carte** déjà vendue.
2. Indiquer le titulaire, le **montant** et les honoraires éventuels.
3. Enregistrer.
4. Remettre le code **R-…** au caissier.

[[CAPTURE|Nouvelle recharge]]

---

## 3. Chargé de clientèle — rendre des cartes

**Menu :** Monétique → Transferts → **Délester vers le chef d’agence**

Sélectionner les cartes encore en stock chez vous, puis valider. Elles reviennent au chef d’agence. Vous ne les renvoyez pas vous-même au siège.

[[CAPTURE|Délester vers le chef d’agence]]

---

## 4. Caissier — encaisser

**Menu :** Monétique → Caisse → **Encaissement**

1. Demander le code au commercial.
2. Le saisir :
   - **V-…** = vente
   - **R-…** = recharge
3. Cliquer sur **Rechercher l’opération**.
4. Vérifier la carte, le client et le montant.
5. Joindre le bordereau de caisse et confirmer.

Après confirmation, l’opération est **encaissée**. Une vente passe alors au statut **vendue**.

Si rien ne s’affiche : code incorrect, déjà encaissé, ou hors de votre agence.

[[CAPTURE|Saisie du code à la caisse]]

[[CAPTURE|Confirmation et bordereau]]

L’onglet **Historique** permet de revoir les encaissements déjà faits.

---

## 5. Chef d’agence — recevoir et répartir

### Demander des cartes

**Menu :** Monétique → Transferts → **Demandes au siège**

Indiquer la **quantité** et un court commentaire, puis envoyer. Vous pouvez annuler tant que le siège n’a pas traité la demande.

[[CAPTURE|Demande au siège]]

### Réceptionner un envoi

**Menu :** Monétique → Transferts → **Réception des cartes**

Ouvrir le transfert en attente, vérifier les cartes, puis **valider la réception** (ou rejeter si le colis ne correspond pas).

[[CAPTURE|Réception des cartes]]

### Donner des cartes à un chargé de clientèle

**Menu :** Monétique → Transferts → **Approvisionnement CC**

1. Choisir le chargé de clientèle.
2. Cocher les cartes du stock agence.
3. Valider.

Il ne verra que ces cartes dans son stock.

[[CAPTURE|Approvisionnement d’un chargé de clientèle]]

### Renvoyer des cartes au siège

**Menu :** Monétique → Transferts → **Retour au siège**

Uniquement des cartes encore en stock à l’agence, non affectées à un chargé de clientèle. Elles reviennent aussitôt au stock du siège.

[[CAPTURE|Retour au siège]]

Le chef d’agence ne saisit pas les ventes ni les recharges, sauf s’il a aussi le rôle de chargé de clientèle.

---

## 6. Monétique siège — mettre en stock et envoyer

### Ajouter des cartes

**Menu :** Monétique → Cartes → **Ajouter**

1. Choisir **Lot** ou **Une carte**.
2. Renseigner le numéro, le lot, la **facture** (fichier obligatoire), les prix, la date de livraison et la date d’expiration.
3. Cliquer sur **Ajouter**.

[[CAPTURE|Ajouter des cartes]]

### Répondre à une agence

**Menu :** Monétique → Transferts → **Demandes d'agences**

- **Créer le transfert** pour envoyer les cartes.
- **Refuser** seulement avec un motif.

**Menu :** Monétique → Transferts → **Nouveau transfert**

Choisir la facture et le lot, cocher les cartes, choisir le chef d’agence, puis envoyer. L’agence doit ensuite réceptionner.

[[CAPTURE|Nouveau transfert]]

---

## 7. En cas de doute

| Je veux… | J’ouvre… |
|----------|----------|
| Vendre | Ventes → **Nouvelle vente** |
| Recharger | Recharges → **Nouvelle recharge** |
| Encaisser | Caisse → **Encaissement** |
| Voir mes cartes | Cartes → **En stock** |
| Demander un réassort | Transferts → **Demandes au siège** |
| Recevoir un colis | Transferts → **Réception des cartes** |
