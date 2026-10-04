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

    // Optional LLM for the shop assistant / product descriptions. Empty key =
    // rule-based answers only. Any OpenAI-compatible endpoint works.
    // rule-based answers only. Any OpenAI-compatible endpoint works.
    'ai' => [
        'api_key'     => env('AI_LLM_API_KEY'),
        'base_url'    => env('AI_LLM_BASE_URL') ?: 'https://api.groq.com/openai/v1',
        'model'       => env('AI_LLM_MODEL') ?: 'llama-3.3-70b-versatile',
        'timeout'     => (int) env('AI_LLM_TIMEOUT', 20),
        'daily_limit' => (int) env('AI_ASSISTANT_DAILY_LIMIT', 200),
    ],

    'momo' => [
        // auto (default): MTN when its keys exist, else Flutterwave when its
        // keys exist, else manual Till flow. Force with: mtn, flutterwave, manual.
        'provider' => env('MOMO_PROVIDER', 'auto'),

        // Shop's own Till/line for the manual flow (no signup needed).
        'till_number' => env('MOMO_TILL_NUMBER'),

        'mtn' => [
            'subscription_key' => env('MTN_SUBSCRIPTION_KEY'),
            'api_user_id'      => env('MTN_API_USER_ID'),
            'api_key'          => env('MTN_API_KEY'),
            'base_url'         => env('MTN_BASE_URL') ?: 'https://sandbox.momodeveloper.mtn.com',
            'environment'      => env('MTN_TARGET_ENV', 'sandbox'),
            'currency'         => env('MTN_CURRENCY', 'UGX'),
        ],
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
