# API Flexcube — Intégration OD (CreateMjrnlbook)

Documentation de l’API utilisée par le module **Opérations diverses** pour transmettre les écritures à Flexcube.

---

## 1. Vue d’ensemble

| Élément | Valeur |
|--------|--------|
| **Usage** | Création d’un journal multi-écritures (batch OD) |
| **Méthode** | `POST` |
| **Content-Type** | `application/json` |
| **Service applicatif** | `App\Services\Integrations\FlexcubeOnlineJournalClient` |
| **Construction du corps** | `App\Support\OdFlexcubeJournalPayload` |
| **Déclenchement** | Action « Intégrer » (maker) sur un classeur OD |

**Flux métier**

1. L’utilisateur crée un brouillon OD (CSV ou saisie manuelle).
2. Il clique sur **Intégrer** et désigne un checker.
3. Laravel transforme les écritures en JSON Flexcube.
4. Appel `CreateMjrnlbook`.
5. En succès : statut *attente de validation*, n° de batch Flexcube enregistré, PDF généré.
6. Le checker valide ensuite dans l’application.

---

## 2. Endpoint

```http
POST {FLEXCUBE_JOURNAL_URL}
```

Exemple (environnement interne) :

```text
https://10.44.221.6:7102/OnlineJournalEntryService/OnlineJournalEntry/CreateMjrnlbook
```

Configurer via `.env` :

```env
FLEXCUBE_JOURNAL_URL=https://10.44.221.6:7102/OnlineJournalEntryService/OnlineJournalEntry/CreateMjrnlbook
FLEXCUBE_JOURNAL_ENTITY=ENTITY_ID1
FLEXCUBE_JOURNAL_SOURCE=FCAT
FLEXCUBE_JOURNAL_BRANCH=501
FLEXCUBE_JOURNAL_VERIFY_SSL=false
```

---

## 3. En-têtes HTTP

| Header | Obligatoire | Source | Description |
|--------|-------------|--------|-------------|
| `USERID` | Oui | **IDFLEX** de l’utilisateur connecté (`users.matricule`) | Identifiant Flexcube du maker |
| `PASSWORD` | Non | `FLEXCUBE_JOURNAL_PASSWORD` | Envoyé seulement s’il est renseigné |
| `ENTITY` | Oui* | `FLEXCUBE_JOURNAL_ENTITY` (défaut `ENTITY_ID1`) | Entité Flexcube |
| `SOURCE` | Oui* | `FLEXCUBE_JOURNAL_SOURCE` (défaut `FCAT`) | Source technique |
| `BRANCH` | Oui* | `FLEXCUBE_JOURNAL_BRANCH` (défaut `501`) | Agence par défaut (headers) |
| `MSGID` | Oui (recommandé) | Généré à chaque appel | Identifiant unique de message (`YmdHis` + 4 chiffres). Évite les rejeux / sessions bloquées |
| `Accept` | Oui | Fixe | `application/json` |
| `Content-Type` | Oui | Fixe | `application/json` |

\*Valeurs par défaut côté config si absentes du `.env`.

> **Note :** `FLEXCUBE_JOURNAL_USERID` n’est plus le mode principal. C’est un **fallback** uniquement si aucun IDFLEX n’est fourni à l’appel. En production, chaque utilisateur doit avoir son IDFLEX renseigné.

---

## 4. Corps de la requête (JSON)

Structure alignée sur l’écran Online Journal Entry Flexcube.

### 4.1 Champs racine

| Champ | Type | Règle Module_FED |
|-------|------|------------------|
| `referenceNo` | string | Toujours `""` |
| `batchNo` | string | Toujours `""` — **Flexcube génère le numéro** |
| `currNo` | number | Nombre de lignes d’écritures |
| `templateCode` | string | `""` |
| `valueDate` | string | `YYYY-MM-DD` (date valeur du classeur / 1ʳᵉ ligne) |
| `bookDate` | string | Identique à `valueDate` |
| `branchCode` | string | Code agence de la 1ʳᵉ ligne (sinon défaut config) |
| `ccy` | string | Devise (défaut `XOF`) |
| `totalDr` / `totalCr` | number | Totaux débit / crédit calculés |
| `maker` | string | IDFLEX du maker |
| `authstat` / `txnstat` | string | `"U"` (non autorisé / non traité) |
| `detbsJrnlTxnDetailList` | array | Lignes d’écritures |
| `detbsBatchMaster` | object | Infos batch |
| `devwsBatchMaster` | object | Infos batch (workspace) |

### 4.2 Ligne d’écriture (`detbsJrnlTxnDetailList[]`)

| Champ API | Source OD | Exemple |
|-----------|-----------|---------|
| `serialNo` | Ordre 1..n | `1` |
| `drCr` | Sens (`D` / `C`) | `"D"` |
| `branchCode` | `code_agence` | `"500"` |
| `accorgl` | Config `FLEXCUBE_JOURNAL_ACCORGL` | `"A"` (compte) |
| `ccy` | Devise | `"XOF"` |
| `amount` | `montant` | `1000` |
| `lcyAmount` | `montant` | `1000` |
| `txnCode` | `code_operation` | `"310"` |
| `account` | `no_compte` | `"251549647881"` |
| `addlText` | `libelle_ecriture` | `"Exemple écriture débit"` |
| `exchRate` | Fixe | `1` |
| `userRefNo`, `instrumentNo`, `acdesc`, `customer`, `referenceNo` | Vides | `""` |

### 4.3 Exemple minimal

```json
{
  "referenceNo": "",
  "batchNo": "",
  "currNo": 2,
  "templateCode": "",
  "valueDate": "2026-08-15",
  "bookDate": "2026-08-15",
  "branchCode": "500",
  "ccy": "XOF",
  "totalDr": 2,
  "totalCr": 2,
  "maker": "MANSOURSK",
  "authstat": "U",
  "txnstat": "U",
  "detbsJrnlTxnDetailList": [
    {
      "referenceNo": "",
      "serialNo": 1,
      "userRefNo": "",
      "drCr": "D",
      "branchCode": "500",
      "accorgl": "A",
      "ccy": "XOF",
      "amount": 2,
      "txnCode": "310",
      "instrumentNo": "",
      "lcyAmount": 2,
      "addlText": "Exemple écriture débit",
      "acdesc": "",
      "customer": "",
      "exchRate": 1,
      "account": "251549647881"
    },
    {
      "referenceNo": "",
      "serialNo": 2,
      "userRefNo": "",
      "drCr": "C",
      "branchCode": "500",
      "accorgl": "A",
      "ccy": "XOF",
      "amount": 2,
      "txnCode": "310",
      "instrumentNo": "",
      "lcyAmount": 2,
      "addlText": "Exemple écriture crédit",
      "acdesc": "",
      "customer": "",
      "exchRate": 1,
      "account": "251549647881"
    }
  ],
  "detbsBatchMaster": {
    "batchNo": "",
    "description": "test 4 API",
    "debit": 2,
    "credit": 2,
    "drEntTotal": 2,
    "crEntTotal": 2,
    "btncomp": ""
  },
  "devwsBatchMaster": {
    "branchCode": "500",
    "batchNumber": "",
    "description": "test 4 API",
    "type": "",
    "lastOperatedBy": "MANSOURSK",
    "lastAuthorisedBy": "",
    "makerdt": "",
    "checkerdt": "",
    "locked": "Y",
    "currNo": 0,
    "debit": 2,
    "credit": 2,
    "authStat": "",
    "uploaded": "",
    "balancing": "Y",
    "sys1": "",
    "position": "",
    "status": ""
  }
}
```

---

## 5. Réponses

### 5.1 Succès

- HTTP typique : **201 Created** (parfois 200).
- Le corps contient le journal créé, avec notamment un **`batchNo`** (ex. `001q`, `8010`).
- Module_FED récupère ce numéro et le stocke dans `od_classeurs.numero_batch` / `numero_piece`.

Exemple (extrait) :

```json
{
  "detbsjrnltxnmaster": {
    "authstat": "U",
    "batchNo": "001q",
    "branchCode": "500"
  }
}
```

### 5.2 Erreurs métier Flexcube

Même sur HTTP 200/400, Flexcube peut renvoyer :

```json
{
  "fcubsErrorResp": {
    "error": [
      {
        "ecode": "RVAL-014",
        "edesc": "User already logged in"
      }
    ]
  }
}
```

| Code | Signification métier | Message affiché à l’utilisateur |
|------|----------------------|----------------------------------|
| `RVAL-014` | Session déjà ouverte pour cet utilisateur | « Vous êtes déjà connecté ailleurs. Déconnectez-vous puis réessayez. » |
| Autre | Refus métier | Libellé `edesc` (simplifié) |

Les détails techniques restent dans le journal dépliable du dialogue d’erreur (pour le support).

### 5.3 Erreurs réseau

| Situation | Message utilisateur |
|-----------|---------------------|
| Timeout / serveur injoignable | Service indisponible / délai dépassé |
| IDFLEX manquant | Identifiant manquant — contacter un administrateur |
| URL non configurée | Service d’intégration non disponible |

---

## 6. Mapping CSV OD → API

Colonnes du fichier d’intégration OD :

| Colonne CSV / saisie | Champ API |
|----------------------|-----------|
| `no_compte` | `account` |
| `sens` (`D`/`C`) | `drCr` |
| `montant` | `amount`, `lcyAmount` |
| `code_operation` | `txnCode` |
| `code_agence` | `branchCode` |
| `libelle_ecriture` | `addlText` |
| `date_de_valeur` | `valueDate` / `bookDate` |
| *(vide)* | `batchNo` (généré par Flexcube) |
| IDFLEX utilisateur | header `USERID` + `maker` |

---

## 7. Variables d’environnement

| Variable | Rôle |
|----------|------|
| `FLEXCUBE_JOURNAL_URL` | URL complète CreateMjrnlbook |
| `FLEXCUBE_JOURNAL_PASSWORD` | Optionnel |
| `FLEXCUBE_JOURNAL_ENTITY` | Défaut `ENTITY_ID1` |
| `FLEXCUBE_JOURNAL_SOURCE` | Défaut `FCAT` |
| `FLEXCUBE_JOURNAL_BRANCH` | Défaut header BRANCH |
| `FLEXCUBE_JOURNAL_CCY` | Devise défaut |
| `FLEXCUBE_JOURNAL_ACCORGL` | `A` compte / `G` GL |
| `FLEXCUBE_JOURNAL_TXN_CODE` | Code op. par défaut si ligne vide |
| `FLEXCUBE_JOURNAL_TIMEOUT` | Timeout HTTP (secondes) |
| `FLEXCUBE_JOURNAL_VERIFY_SSL` | `false` souvent nécessaire en interne |
| `FLEXCUBE_JOURNAL_USERID` | Fallback seulement (non recommandé) |

---

## 8. Fichiers code concernés

| Fichier | Rôle |
|---------|------|
| `app/Services/Integrations/FlexcubeOnlineJournalClient.php` | Client HTTP |
| `app/Support/OdFlexcubeJournalPayload.php` | Mapping OD → JSON |
| `app/Http/Controllers/OperationDiverseController.php` | Action `pieceComptableIntegrer` |
| `app/Support/FlashDialog.php` | Messages d’erreur simplifiés |
| `config/services.php` | Clé `flexcube_online_journal` |

---

## 9. Test Postman (référence)

1. Méthode `POST`, URL = `FLEXCUBE_JOURNAL_URL`.
2. Headers : `USERID`, `ENTITY`, `SOURCE`, `BRANCH` (+ `PASSWORD` / `MSGID` si besoin).
3. Body raw JSON (voir §4.3) avec `batchNo` vide.
4. Vérifier HTTP 201 et présence de `batchNo` dans la réponse.

---

## 10. Prérequis côté application

- L’utilisateur maker doit avoir un **IDFLEX** (`matricule`) renseigné.
- Le serveur Laravel doit pouvoir joindre l’IP/port Flexcube (réseau / VPN).
- Un checker éligible doit être désigné à l’intégration.
