<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Flexcube Online Journal Entry — CreateMjrnlbook (intégration OD).
    | USERID = IDFLEX de l’utilisateur connecté (pas une constante .env).
    | FLEXCUBE_JOURNAL_USERID reste un fallback optionnel uniquement.
    */
    'flexcube_online_journal' => [
        'url' => env(
            'FLEXCUBE_JOURNAL_URL',
            'https://10.44.221.8:7102/OnlineJournalEntryService/OnlineJournalEntry/CreateMjrnlbook'
        ),
        'userid' => env('FLEXCUBE_JOURNAL_USERID'), // fallback optionnel
        'password' => env('FLEXCUBE_JOURNAL_PASSWORD'),
        'entity' => env('FLEXCUBE_JOURNAL_ENTITY', 'ENTITY_ID1'),
        'source' => env('FLEXCUBE_JOURNAL_SOURCE', 'FCAT'),
        'branch' => env('FLEXCUBE_JOURNAL_BRANCH', '501'),
        'ccy' => env('FLEXCUBE_JOURNAL_CCY', 'XOF'),
        'accorgl' => env('FLEXCUBE_JOURNAL_ACCORGL', 'A'),
        'txn_code_default' => env('FLEXCUBE_JOURNAL_TXN_CODE', 'MIG'),
        'last_operated_by' => env('FLEXCUBE_JOURNAL_LAST_OPERATED_BY', 'APIUSER1'),
        'timeout' => (int) env('FLEXCUBE_JOURNAL_TIMEOUT', 120),
        /* Certificat interne souvent auto-signé */
        'verify_ssl' => (bool) filter_var(
            env('FLEXCUBE_JOURNAL_VERIFY_SSL', 'false'),
            FILTER_VALIDATE_BOOL
        ),
    ],

    /*
    | Format CSV local (séparateur, dates, montants) — utilisé par OD / export écritures.
    */
    'ecritures_comptables_import' => [
        'csv_delimiter' => env('ECRITURES_COMPTABLES_IMPORT_CSV_DELIMITER', ';'),
        'csv_date_format' => env('ECRITURES_COMPTABLES_IMPORT_CSV_DATE_FORMAT', 'd/m/Y'),
        'csv_decimal_separator' => env('ECRITURES_COMPTABLES_IMPORT_CSV_DECIMAL_SEPARATOR', '.'),
        'csv_thousands_separator' => env('ECRITURES_COMPTABLES_IMPORT_CSV_THOUSANDS_SEPARATOR', ''),
        'csv_montant_decimals' => (int) env('ECRITURES_COMPTABLES_IMPORT_CSV_MONTANT_DECIMALS', 3),
        'csv_include_bom' => env('ECRITURES_COMPTABLES_IMPORT_CSV_INCLUDE_BOM', 'true'),
        'devise' => env('ECRITURES_COMPTABLES_IMPORT_DEVISE', 'XOF'),
    ],

    /*
    | Gateway Python Reconciliation (reconc.py — uvicorn --port 8002).
    | Le navigateur n’appelle jamais le gateway directement : Laravel proxyfie.
    */
    'reconciliation_gateway' => [
        'url' => env('RECONCILIATION_GATEWAY_URL', 'http://127.0.0.1:8002'),
        'api_key' => env('RECONCILIATION_GATEWAY_KEY'),
        'api_key_header' => env('RECONCILIATION_GATEWAY_KEY_HEADER', 'apikey'),
        'timeout' => (int) env('RECONCILIATION_GATEWAY_TIMEOUT', 180),
        'verify_ssl' => (bool) filter_var(
            env('RECONCILIATION_GATEWAY_VERIFY_SSL', 'true'),
            FILTER_VALIDATE_BOOL
        ),
    ],

    /*
    | En-tête imprimable (bordereau caisse Coficarte) — surcharge via .env si besoin.
    */
    'coficarte' => [
        'bordereau' => [
            'raison_sociale' => env('COFICARTE_BORDEREAU_SOCIETE', 'Cofina'),
            'sous_titre' => env('COFICARTE_BORDEREAU_SOUS_TITRE', 'Compagnie Financière Africaine'),
            'ligne_adresse' => env('COFICARTE_BORDEREAU_ADRESSE', 'Cofina Sénégal'),
            'telephones' => env('COFICARTE_BORDEREAU_TEL', '(+221) 33 879 90 90'),
            'email' => env('COFICARTE_BORDEREAU_EMAIL', 'service.client@cac.cofinacorps.com'),
        ],
    ],

];
