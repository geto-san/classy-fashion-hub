<?php

/*
|--------------------------------------------------------------------------
| Classy Fashion Hub settings
|--------------------------------------------------------------------------
|
| Environment values are read HERE, once, so they survive `php artisan
| config:cache` (run by `artisan optimize` on deploy). Never call env()
| from application code: with a cached config it returns null.
*/

return [
    'flutterwave' => [
        'public_key'      => env('FLUTTERWAVE_PUBLIC_KEY'),
        'secret_key'      => env('FLUTTERWAVE_SECRET_KEY'),
        'secret_hash'     => env('FLUTTERWAVE_SECRET_HASH'),

        // true = Flutterwave test keys (FLWSECK_TEST-...), false = live keys.
        'sandbox'         => filter_var(env('FLUTTERWAVE_SANDBOX', true), FILTER_VALIDATE_BOOLEAN),

        // Minutes an unapproved mobile-money prompt stays open.
        'pending_minutes' => (int) env('FLUTTERWAVE_PENDING_MINUTES', 30),
    ],

    'seed' => [
        // Create the worker/customer demo accounts on a production database.
        'demo'              => filter_var(env('SEED_DEMO', false), FILTER_VALIDATE_BOOLEAN),

        // Optional: choose the production credentials instead of random ones.
        'admin_email'       => env('SEED_ADMIN_EMAIL'),
        'admin_password'    => env('SEED_ADMIN_PASSWORD'),
        'worker_password'   => env('SEED_WORKER_PASSWORD'),
        'customer_password' => env('SEED_CUSTOMER_PASSWORD'),
    ],
];
