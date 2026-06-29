<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Taille max. des pièces justificatives OD (Ko)
    |--------------------------------------------------------------------------
    | 25600 Ko = 25 Mo. Ajuster via OD_MAX_UPLOAD_MB dans .env si besoin.
    */
    'max_upload_kb' => (int) env('OD_MAX_UPLOAD_MB', 25) * 1024,

];
