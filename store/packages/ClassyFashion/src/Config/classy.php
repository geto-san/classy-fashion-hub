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
    'client_admin' => filter_var(env('CLASSY_CLIENT_ADMIN', true), FILTER_VALIDATE_BOOLEAN),

    'momo' => [
        // auto (default): MTN when its keys exist, else the manual Till flow.
        // Force with: mtn, manual.
        'provider' => env('MOMO_PROVIDER', 'auto'),

        // Shop's own Till/line for the manual flow (no signup needed).
        'till_number' => env('MOMO_TILL_NUMBER'),

        // Minutes an unapproved mobile-money prompt stays open.
        'pending_minutes' => (int) env('MOMO_PENDING_MINUTES', 30),

        'mtn' => [
            'subscription_key' => env('MTN_SUBSCRIPTION_KEY'),
            'api_user_id' => env('MTN_API_USER_ID'),
            'api_key' => env('MTN_API_KEY'),
            'base_url' => env('MTN_BASE_URL') ?: 'https://sandbox.momodeveloper.mtn.com',
            'environment' => env('MTN_TARGET_ENV', 'sandbox'),
            'currency' => env('MTN_CURRENCY', 'UGX'),
        ],
    ],

    // Optional LLM for the shop assistant / product descriptions. Empty key =
    // rule-based answers only. Any OpenAI-compatible endpoint works.
    'ai' => [
        'api_key' => env('OPENAI_API_KEY') ?: env('AI_LLM_API_KEY'),
        'base_url' => env('OPENAI_API_KEY') ? 'https://api.openai.com/v1' : (env('AI_LLM_BASE_URL') ?: 'https://api.groq.com/openai/v1'),
        'model' => env('OPENAI_API_KEY') ? env('OPENAI_MODEL', 'gpt-4.1-mini') : (env('AI_LLM_MODEL') ?: 'llama-3.3-70b-versatile'),
        'timeout' => (int) env('AI_LLM_TIMEOUT', 20),
        'daily_limit' => (int) env('AI_ASSISTANT_DAILY_LIMIT', 200),
    ],

    'seed' => [
        // Create the worker/customer demo accounts on a production database.
        'demo' => filter_var(env('SEED_DEMO', false), FILTER_VALIDATE_BOOLEAN),

        // Optional: choose the production credentials instead of random ones.
        'admin_email' => env('SEED_ADMIN_EMAIL'),
        'admin_password' => env('SEED_ADMIN_PASSWORD'),
        'worker_password' => env('SEED_WORKER_PASSWORD'),
        'customer_password' => env('SEED_CUSTOMER_PASSWORD'),
    ],
];
