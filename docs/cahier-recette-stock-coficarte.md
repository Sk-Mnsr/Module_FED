# Cahier de recette — Gestion de stock & Coficarte (Visa)

| Élément | Valeur |
|---------|--------|
| **Référence** | CR-STOCK-COFICARTE-2026 |
| **Application** | COFI-COMPTA / Module_FED |
| **Sources** | *Cahier des Charges carte visa.pdf* (schéma comptable Achat Carte + ventilations) + implémentation applicative |
| **Modules** | Gestion de stock (articles FED) · Monétique / Coficarte |
| **Version** | 1.0 |
| **Date** | 31/08/2026 |

---

## 1. Objectif du document

Valider en recette :

1. Le **module Gestion de stock** (articles / demandes d’approvisionnement FED).
2. Le **workflow Coficarte** (stock siège → agence → CC → vente → encaissement → recharge).
3. Le **schéma comptable Achat Carte** et ses **ventilations** (référence cahier des charges), en distinguant :
   - ce qui est **attendu métier / comptable** (écriture OD / Flexcube) ;
   - ce qui est **couvert par l’application** aujourd’hui (stock unitaire, prix, traçabilité).

> **Point d’attention recette**  
> Dans Module_FED, l’**achat carte** enregistre le stock (`prix_achat`, facture, BL) **sans générer automatiquement** les écritures du schéma comptable. Les scénarios §4 mesurent donc :
> - **fonctionnel applicatif** (OK / KO dans l’app) ;
> - **comptable** (OK / KO via OD manuelle ou intégration Flexcube, hors posting auto).

---

## 2. Périmètre & hors périmètre

### 2.1 Dans le périmètre

| Domaine | Contenu |
|---------|---------|
| Stock articles | Inventaire, mouvements, demandes d’approvisionnement |
| Coficarte | Enrôlement / achat siège, demandes, transferts, ventilation agence, retour siège, vente, encaissement, recharge, seuils, pilotage |
| Schéma comptable | Achat siège, constatation stock, ventilation agence, vente, sortie de stock, retour siège |
| Contrôles | Rôles, traçabilité, seuils, séparation vente ≠ encaissement |

### 2.2 Hors périmètre (sauf mention)

- Intégration GTP (encaissement SI externe)
- API partenaires recharge (solde carte réel)
- Module FED dépenses / Budget / OD génériques hors schéma carte
- Campagnes marketing (vérifier présence écran ; KPIs détaillés optionnels)

---

## 3. Acteurs & comptes de test

| Rôle applicatif | Slug | Profil métier CdC |
|-----------------|------|-------------------|
| SuperAdmin / IT | `it` | Bypass |
| Responsable monétique | `monetique` | Stock central, enrôlement, prix, transferts |
| Ops monétique | `monetique_ops` | Vue / opérations |
| Chef d’agence | `ca` | Réception, stock agence, appro CC, demandes |
| Chargé de clientèle | `cc` | Vente, recharge |
| Caissier | `caissier` | Encaissement |
| Responsable stock | `responsable_stock` | Stock articles FED |
| Demandeur | `demandeur` / utilisateur | Demandes d’approvisionnement articles |

**Données prérequises**

- Au moins 1 agence active
- 1 CA et 1 CC rattachés à l’agence
- 1 caissier (même agence ou habilitation caisse)
- Articles de stock FED (pour module stock)
- Seuils stock Coficarte (central + agence) paramétrés

---

## 4. Référence — Schéma comptable Achat Carte (CdC)

Montants types pour les jeux de test (à adapter) :

| Variable | Exemple |
|----------|---------|
| Prix d’achat TTC (1 carte) | **PA** = 2 000 FCFA |
| Quantité lot | **Q** = 10 → **PA × Q** = 20 000 |
| Prix de vente HT | **PV_HT** |
| Taxe vente | **TVA** (compte 3324…) |
| Montant commissions dues / trésorerie | **Mtt com** (compte 101…) |
| Prix de vente TTC caisse | **PV** (souvent PV_HT + taxe) |
| Première recharge | **R1** (encaissée avec la vente si pack) |

### 4.1 Achat de cartes au niveau Siège

| # | Nature | Sens | N° compte (masque CdC) | Libellé | Montant |
|---|--------|------|------------------------|---------|---------|
| AC-01 | Achat de cartes | **Débit** | `611*********` | Achat carte | PA × Q (TTC) |
| AC-02 | Achat de cartes | **Crédit** | `1141********` | Banque | PA × Q (TTC) |

**Sens métier** : constatation de l’achat fournisseur / paiement banque.

### 4.2 Constatation stock de carte au Siège

| # | Nature | Sens | N° compte | Libellé | Montant |
|---|--------|------|-----------|---------|---------|
| CS-01 | Constatation stock | **Débit** | `32**********` | Stock de carte Siège | PA × Q |
| CS-02 | Constatation stock | **Crédit** | `612*********` | Variation de stock | PA × Q |

**Sens métier** : entrée en stock siège au coût d’achat.

### 4.3 Ventilation carte au niveau agence

| # | Nature | Sens | N° compte | Libellé | Montant |
|---|--------|------|-----------|---------|---------|
| VA-01 | Ventilation stock | **Débit** | `32**********` | Stock de carte **Agence** | PA × q |
| VA-02 | Ventilation stock | **Crédit** | `32**********` | Stock de carte **Siège** | PA × q |

**Sens métier** : transfert de stock siège → agence (même classe 32, ventilation analytique / sous-compte agence).

### 4.4 Vente de carte en agence

| # | Nature | Sens | N° compte | Libellé | Montant |
|---|--------|------|-----------|---------|---------|
| VC-01 | Vente de carte | **Débit** | `101*********` | Compte de trésorerie | Mtt com dues |
| VC-02 | Vente de carte | **Crédit** | `7************` | Vente de carte | PV_HT |
| VC-03 | Vente de carte | **Crédit** | `3324********` | Taxe sur vente carte | Taxe |
| SS-01 | Sortie de stock | **Débit** | `611*********` | Achat carte | PA |
| SS-02 | Sortie de stock | **Crédit** | `32**********` | Stock de carte **Agence** | PA |

**Sens métier** : encaissement / produit + sortie du stock agence au coût d’achat.

### 4.5 Retour de stock au siège

| # | Nature | Sens | N° compte | Libellé | Montant |
|---|--------|------|-----------|---------|---------|
| RS-01 | Retour siège | **Débit** | `32**********` | Stock de carte **Siège** | PA × q |
| RS-02 | Retour siège | **Crédit** | `32**********` | Stock de carte **Agence** | PA × q |

**Sens métier** : inverse de la ventilation agence.

### 4.6 Matrice couverture applicative ↔ comptable

| Opération CdC | Événement app Coficarte | Écriture auto app | Recette comptable |
|---------------|-------------------------|-------------------|-------------------|
| Achat siège | `/monetique/cartes/ajouter` (`carte_creee`, `prix_achat`) | Non | OD / Flexcube manuelle ou futur |
| Constatation stock siège | Même événement (cartes `en_stock` siège) | Non | Idem |
| Ventilation agence | Transfert validé (réception CA) | Non | Idem |
| Vente + sortie stock | Encaissement vente (`vendu`) | Non | Idem |
| Retour siège | `/monetique/agence/retour-cartes` | Non | Idem |

---

## 5. Scénarios de recette — Gestion de stock (articles)

### GS-01 — Consultation inventaire

| | |
|--|--|
| **Prérequis** | Rôle `responsable_stock` ou `it` |
| **Étapes** | 1. Ouvrir `/stock` · 2. Vérifier liste articles + `stock_actuel` |
| **Résultat attendu** | Affichage cohérent avec la base |
| **Statut** | ☐ OK · ☐ KO · ☐ N/A |

### GS-02 — Mouvement entrée manuelle

| | |
|--|--|
| **Étapes** | `/stock/movements` ou action entrée · Quantité + motif |
| **Résultat attendu** | `stock_actuel` incrémenté · mouvement type `entree` |
| **Statut** | ☐ OK · ☐ KO · ☐ N/A |

### GS-03 — Mouvement sortie manuelle

| | |
|--|--|
| **Résultat attendu** | Décrément · type `sortie` · refus si stock insuffisant (si contrôlé) |
| **Statut** | ☐ OK · ☐ KO · ☐ N/A |

### GS-04 — Demande d’approvisionnement → Livraison

| | |
|--|--|
| **Acteurs** | Demandeur → Responsable stock |
| **Étapes** | 1. `/demandes-approvisionnement/create` · 2. Traitement `livree` avec mapping article |
| **Résultat attendu** | Statut `livree` · sortie stock · mouvement lié |
| **Statut** | ☐ OK · ☐ KO · ☐ N/A |

### GS-05 — Demande rejetée

| | |
|--|--|
| **Résultat attendu** | Statut `rejetee` · **aucun** décrément stock |
| **Statut** | ☐ OK · ☐ KO · ☐ N/A |

---

## 6. Scénarios de recette — Coficarte (fonctionnel)

### CF-01 — Achat / enrôlement lot au siège

| | |
|--|--|
| **Acteur** | `monetique` |
| **URL** | `/monetique/cartes/ajouter` |
| **Données** | Réf. facture, facture, BL, `prix_achat`=PA, `prix_vente`, dates, quantité Q |
| **Résultat attendu** | Q cartes `en_stock`, `agence_id` null · événement `carte_creee` · PA stocké |
| **Lien comptable** | Déclenche besoins **AC-01/02** + **CS-01/02** (hors auto) |
| **Statut** | ☐ OK · ☐ KO · ☐ N/A |

### CF-02 — Paramétrage / modification prix de vente

| | |
|--|--|
| **URL** | `/monetique/cartes/modifier-prix` |
| **Résultat attendu** | Maj prix · mouvement `prix_vente_maj` · n’altère pas `prix_achat` |
| **Statut** | ☐ OK · ☐ KO · ☐ N/A |

### CF-03 — Demande d’approvisionnement agence

| | |
|--|--|
| **Acteur** | CA |
| **URL** | `/monetique/agence/demandes-approvisionnement` |
| **Résultat attendu** | Demande `en_attente` · mail monétique (si queue active) |
| **Statut** | ☐ OK · ☐ KO · ☐ N/A |

### CF-04 — Refus demande

| | |
|--|--|
| **Acteur** | Monétique |
| **Résultat attendu** | `refusee` · mail chef · stock inchangé |
| **Statut** | ☐ OK · ☐ KO · ☐ N/A |

### CF-05 — Transfert siège → agence (ventilation physique)

| | |
|--|--|
| **Étapes** | Création transfert (lié ou non à demande) → réception CA `/monetique/transferts/en-attente` |
| **Résultat attendu** | Cartes `en_stock` avec `agence_id` = agence · pool (non assignées) · demande `acceptee` / `partielle` |
| **Lien comptable** | **VA-01 / VA-02** pour q cartes × PA |
| **Statut** | ☐ OK · ☐ KO · ☐ N/A |

### CF-06 — Approvisionnement CC

| | |
|--|--|
| **URL** | `/monetique/agence/approvisionnement-cc` |
| **Résultat attendu** | `assigned_to_user_id` = CC · mouvement `assignation_cc` |
| **Statut** | ☐ OK · ☐ KO · ☐ N/A |

### CF-07 — Délester CC → pool chef

| | |
|--|--|
| **URL** | `/monetique/cc/delester-chef-agence` |
| **Résultat attendu** | Carte reste agence, non assignée |
| **Statut** | ☐ OK · ☐ KO · ☐ N/A |

### CF-08 — Vente carte (CC) → attente caisse

| | |
|--|--|
| **URL** | `/monetique/ventes/nouveau` |
| **Données** | KYC, pack/hors pack, apporteur, 1ʳᵉ recharge éventuelle |
| **Résultat attendu** | Carte `en_attente_encaissement` · vente `en_attente` · code encaissement · **pas** encore sortie stock comptable |
| **Statut** | ☐ OK · ☐ KO · ☐ N/A |

### CF-09 — Encaissement vente (séparation des rôles)

| | |
|--|--|
| **Acteur** | Caissier (**≠** vendeur CC) |
| **URL** | `/monetique/encaissements` |
| **Résultat attendu** | Bordereau obligatoire · vente `encaisse` · carte `vendu` · `activated_at` · montant = PV (+ R1 si pack) |
| **Lien comptable** | **VC-01..03** + **SS-01/02** |
| **Contrôle négatif** | ☐ Le CC ne peut pas encaisser sa propre vente (selon règles métier) |
| **Statut** | ☐ OK · ☐ KO · ☐ N/A |

### CF-10 — Recharge + encaissement

| | |
|--|--|
| **URL** | `/monetique/recharges/nouveau` puis caisse |
| **Résultat attendu** | Recharge en attente → encaissée · honoraires inclus · bordereau |
| **Statut** | ☐ OK · ☐ KO · ☐ N/A |

### CF-11 — Retour stock agence → siège

| | |
|--|--|
| **URL** | `/monetique/agence/retour-cartes` |
| **Résultat attendu** | `agence_id` null · `en_stock` siège · mouvement `retour_siege` |
| **Lien comptable** | **RS-01 / RS-02** |
| **Statut** | ☐ OK · ☐ KO · ☐ N/A |

### CF-12 — Seuils & alertes

| | |
|--|--|
| **URL** | `/monetique/parametrage/seuils-stock` |
| **Étapes** | Descendre le stock sous `min_cards` (transfert / vente) |
| **Résultat attendu** | Alerte UI et/ou mail monétique |
| **Statut** | ☐ OK · ☐ KO · ☐ N/A |

### CF-13 — Traçabilité carte

| | |
|--|--|
| **URL** | `/monetique/cartes/{id}/mouvements` |
| **Résultat attendu** | Historique chronologique des événements du cycle de vie |
| **Statut** | ☐ OK · ☐ KO · ☐ N/A |

### CF-14 — Pilotage / KPI stock & ventes

| | |
|--|--|
| **URL** | `/monetique/pilotage` |
| **Résultat attendu** | Stock central / agence / indicateurs ventes cohérents avec les opérations |
| **Statut** | ☐ OK · ☐ KO · ☐ N/A |

---

## 7. Scénarios de recette — Ventilations comptables (jeux chiffrés)

Utiliser un **lot de test** isolé (référence facture unique, ex. `RECETTE-VISA-2026-08`).

### COMPTA-01 — Achat + constatation siège

**Hypothèse** : Q = 10 cartes, PA = 2 000 → total **20 000**.

| Ligne | Compte | D | C | Montant | OK |
|-------|--------|---|---|---------|----|
| Achat | 611… Achat carte | X | | 20 000 | ☐ |
| Achat | 1141… Banque | | X | 20 000 | ☐ |
| Constatation | 32… Stock Siège | X | | 20 000 | ☐ |
| Constatation | 612… Variation stock | | X | 20 000 | ☐ |

**Contrôle app** : 10 cartes en stock siège, `prix_achat` = 2 000, pièces facture + BL. ☐

### COMPTA-02 — Ventilation vers agence

**Hypothèse** : q = 4 cartes transférées et réceptionnées → **8 000**.

| Ligne | Compte | D | C | Montant | OK |
|-------|--------|---|---|---------|----|
| Ventilation | 32… Stock **Agence** | X | | 8 000 | ☐ |
| Ventilation | 32… Stock **Siège** | | X | 8 000 | ☐ |

**Contrôle app** : 4 cartes `agence_id` = agence test · 6 restantes au siège. ☐

### COMPTA-03 — Vente d’1 carte en agence

**Hypothèse exemple** (à renseigner avec la fiscalité réelle) :

| Variable | Valeur test |
|----------|-------------|
| PA | 2 000 |
| PV_HT | ……… |
| Taxe (3324) | ……… |
| Mtt com / trésorerie (101) | ……… |

| Ligne | Compte | D | C | Montant | OK |
|-------|--------|---|---|---------|----|
| Vente | 101… Trésorerie | X | | Mtt com | ☐ |
| Vente | 7… Vente carte | | X | PV_HT | ☐ |
| Vente | 3324… Taxe | | X | Taxe | ☐ |
| Sortie stock | 611… Achat carte | X | | 2 000 | ☐ |
| Sortie stock | 32… Stock Agence | | X | 2 000 | ☐ |

**Contrôle app** : carte `vendu` · encaissement OK · stock agence −1. ☐  
**Équilibre** : Débits = Crédits sur le lot d’écritures. ☐

### COMPTA-04 — Retour siège (2 cartes)

| Ligne | Compte | D | C | Montant | OK |
|-------|--------|---|---|---------|----|
| Retour | 32… Stock Siège | X | | 4 000 | ☐ |
| Retour | 32… Stock Agence | | X | 4 000 | ☐ |

**Contrôle app** : 2 cartes de retour au siège. ☐

### COMPTA-05 — Cohérence stock théorique vs physique

| Contrôle | Méthode | OK |
|----------|---------|----|
| Stock siège app = cartes `en_stock` sans agence | `/monetique/cartes/en-stock` | ☐ |
| Stock agence app = cartes agence non vendues | Suivi CA / en-stock | ☐ |
| Valorisation = Σ `prix_achat` des cartes en stock | Export / requête | ☐ |
| Après vente, valorisation agence diminuée de PA | COMPTA-03 | ☐ |

---

## 8. Contrôles transverses & non-régression

| ID | Contrôle | OK |
|----|----------|----|
| CTRL-01 | Séparation vente (CC) ≠ encaissement (Caissier) | ☐ |
| CTRL-02 | Numéro de carte unique (refus doublon à l’enrôlement) | ☐ |
| CTRL-03 | Transfert partiel / demande `partielle` | ☐ |
| CTRL-04 | Annulation demande encore `en_attente` | ☐ |
| CTRL-05 | Habilitations : CA ne crée pas de cartes siège | ☐ |
| CTRL-06 | CC ne voit que ses cartes assignées (vente) | ☐ |
| CTRL-07 | Journal d’audit / mouvements carte complets | ☐ |
| CTRL-08 | Mails : demande, transfert, encaissement attente, stock bas (si SMTP/queue OK) | ☐ |
| CTRL-09 | Export PDF bon de transfert | ☐ |
| CTRL-10 | Aucune écriture Flexcube auto à l’achat (comportement actuel documenté) | ☐ |

---

## 9. Jeu de données recommandé

| Élément | Valeur suggérée |
|---------|-----------------|
| Réf. facture test | `RECETTE-VISA-YYYYMMDD` |
| Agence | Code réel (ex. 501) |
| Lot initial | 10 cartes |
| PA | 2 000 |
| PV | Selon paramétrage métier |
| Apporteur | 1 apporteur rattaché à l’agence |
| Seuil central | ex. min = 5 |
| Seuil agence | ex. min = 2 |

---

## 10. Synthèse des résultats

| Bloc | Nb scénarios | OK | KO | N/A | Commentaires |
|------|--------------|----|----|-----|--------------|
| Gestion de stock (GS) | 5 | | | | |
| Coficarte fonctionnel (CF) | 14 | | | | |
| Comptabilité / ventilations | 5 | | | | |
| Contrôles transverses | 10 | | | | |
| **Total** | **34** | | | | |

**Verdict global** : ☐ Recette acceptée · ☐ Acceptée avec réserves · ☐ Refusée

**Réserves / écarts** :

1. ………………………………………………………………………………  
2. ………………………………………………………………………………  
3. ………………………………………………………………………………  

---

## 11. Signatures

| Rôle | Nom | Date | Signature |
|------|-----|------|-----------|
| Recette métier Monétique | | | |
| Recette Comptabilité | | | |
| Chef de projet / IT | | | |

---

## 12. Annexes

### A. Mapping écrans

| Besoin CdC | URL app |
|------------|---------|
| Stock central / enrôlement | `/monetique/cartes/ajouter`, `/monetique/cartes/en-stock` |
| Approvisionnement agences | `/monetique/agence/demandes-approvisionnement`, `/monetique/transferts/*` |
| Distribution CC | `/monetique/agence/approvisionnement-cc` |
| Vente | `/monetique/ventes/nouveau` |
| Encaissement | `/monetique/encaissements` |
| Recharge | `/monetique/recharges/nouveau` |
| Retour siège | `/monetique/agence/retour-cartes` |
| KPI / stock | `/monetique/pilotage` |
| Stock articles FED | `/stock`, `/demandes-approvisionnement` |

### B. Diagramme condensé cycle carte ↔ écritures

```
[Achat siège] ──AC+CS──► Stock Siège (32)
        │
        │ transfert réception
        ▼
   Ventilation VA ──► Stock Agence (32)
        │
        │ vente + encaissement
        ▼
   Vente VC + Sortie SS ──► Carte vendue / stock agence −PA
        │
        │ (option) retour
        ▼
   Retour RS ──► Stock Siège ← Stock Agence
```

### C. Écart connu (à traiter en backlog si requis)

| Exigence CdC | État Module_FED |
|--------------|-----------------|
| Posting automatique schéma Achat / Ventilation / Vente | **Non implémenté** — stock + montants seulement |
| Intégration GTP | Hors app actuelle |
| Solde carte recharge temps réel | Dépend SI externe |

---

*Document généré pour la recette COFINA — Gestion de stock & Coficarte Visa, sur la base du schéma comptable « Achat Carte » et des ventilations du cahier des charges joint.*
