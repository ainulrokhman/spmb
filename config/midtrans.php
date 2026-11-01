<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Midtrans Credentials
    |--------------------------------------------------------------------------
    |
    | Keys live in .env, never in code. Sandbox keys are used unless
    | MIDTRANS_PRODUCTION is set to true.
    |
    */

    'server_key' => env('MIDTRANS_SERVER_KEY'),
    'client_key' => env('MIDTRANS_CLIENT_KEY'),
    'is_production' => env('MIDTRANS_PRODUCTION', false),

    'snap_url' => env('MIDTRANS_PRODUCTION', false)
        ? 'https://app.midtrans.com/snap/v1/transactions'
        : 'https://app.sandbox.midtrans.com/snap/v1/transactions',

    'snap_js_url' => env('MIDTRANS_PRODUCTION', false)
        ? 'https://app.midtrans.com/snap/snap.js'
        : 'https://app.sandbox.midtrans.com/snap/snap.js',
];
