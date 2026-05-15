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
    | Import comptable Flex SN (écran « Indications du fichier ») :
    | CSV ; séparateur point pour les décimales des montants ; date jj/mm/aaaa ;
    | année FY(AAAA) ; mois MXX ; user_id = utilisateur Flexcube ; max 15 Mo.
    */
    'ecritures_comptables_import' => [
        /* POST serveur → API Flex Compta (sn) ; surcharge via .env */
        'url' => env(
            'ECRITURES_COMPTABLES_IMPORT_URL',
            'https://backend_flex_compta_sn.cofinaonline.com/od/upload/'
        ),
        'api_key' => env('ECRITURES_COMPTABLES_IMPORT_KEY'),
        /* Navigateur Flex : -H 'apikey: …' */
        'api_key_header' => env('ECRITURES_COMPTABLES_IMPORT_KEY_HEADER', 'apikey'),
        'file_field' => env('ECRITURES_COMPTABLES_IMPORT_FILE_FIELD', 'file'),
        'csv_filename' => env('ECRITURES_COMPTABLES_IMPORT_CSV_NAME', 'RQFT.csv'),
        'csv_delimiter' => env('ECRITURES_COMPTABLES_IMPORT_CSV_DELIMITER', ';'),
        'csv_date_format' => env('ECRITURES_COMPTABLES_IMPORT_CSV_DATE_FORMAT', 'd/m/Y'),
        'csv_decimal_separator' => env('ECRITURES_COMPTABLES_IMPORT_CSV_DECIMAL_SEPARATOR', '.'),
        'csv_thousands_separator' => env('ECRITURES_COMPTABLES_IMPORT_CSV_THOUSANDS_SEPARATOR', ''),
        /* Ex. écran Flex 599.100 → 3 décimales ; surcharge possible */
        'csv_montant_decimals' => (int) env('ECRITURES_COMPTABLES_IMPORT_CSV_MONTANT_DECIMALS', 3),
        'csv_include_bom' => env('ECRITURES_COMPTABLES_IMPORT_CSV_INCLUDE_BOM', 'true'),
        /* multipart = comme le front ; raw = corps CSV seul (si doc / autre env le demande) */
        'body_mode' => env('ECRITURES_COMPTABLES_IMPORT_BODY_MODE', 'multipart'),
        'timeout' => (int) env('ECRITURES_COMPTABLES_IMPORT_TIMEOUT', 120),
        'verify_ssl' => (bool) filter_var(
            env('ECRITURES_COMPTABLES_IMPORT_VERIFY_SSL', 'true'),
            FILTER_VALIDATE_BOOL
        ),
        'origin' => env('ECRITURES_COMPTABLES_IMPORT_ORIGIN', 'https://flexcomptasn.cofinaonline.com'),
        'referer' => env('ECRITURES_COMPTABLES_IMPORT_REFERER', 'https://flexcomptasn.cofinaonline.com/'),
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
