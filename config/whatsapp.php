<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Gateways
    |--------------------------------------------------------------------------
    |
    | GOWA is the foundation's self-hosted go-whatsapp-web-multidevice
    | server: one set of Basic Auth credentials (kept here, sourced
    | from .env) protects every device slot on it. Fonnte is a cloud
    | gateway: it needs no global credential because each device
    | carries its own token, stored encrypted on the device row.
    |
    */

    'gowa' => [
        'base_url' => env('GOWA_BASE_URL'),
        'username' => env('GOWA_USERNAME'),
        'password' => env('GOWA_PASSWORD'),
        'timeout' => (int) env('GOWA_TIMEOUT', 15),
    ],

    'fonnte' => [
        'base_url' => env('FONNTE_BASE_URL', 'https://api.fonnte.com'),
        'timeout' => (int) env('FONNTE_TIMEOUT', 15),
    ],

];
