<?php
// config/services.php
// Agregar estos bloques al array del archivo config/services.php existente

return [

    // ... otros servicios existentes (mailgun, postmark, ses) ...

    /*
    |--------------------------------------------------------------------------
    | Moodle REST API
    |--------------------------------------------------------------------------
    */
    'moodle' => [
        'url'   => env('MOODLE_URL'),
        'token' => env('MOODLE_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | ePayco
    |--------------------------------------------------------------------------
    */
    'epayco' => [
        'cust_id'    => env('EPAYCO_CUST_ID'),
        'secret_key' => env('EPAYCO_SECRET_KEY'),
    ],

];
