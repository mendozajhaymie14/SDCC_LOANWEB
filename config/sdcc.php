<?php

return [

    // Seed demo users for each of the 6 roles when run from local/testing.
    'seed_demo_users' => filter_var(env('SEED_DEMO_USERS', false), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | GMail-only e-mail rule
    |--------------------------------------------------------------------------
    |
    | The San Dionisio Credit Cooperative still only accepts @gmail.com e-mail
    | addresses for new accounts. Flip this off with one env var in any file:
    | GMAIL_ONLY_EMAILS=false (the password vars below are ignored — never
    | print them anywhere).
    |
    */

    'gmail_only_email' => (bool) env('GMAIL_ONLY_EMAILS', true),

];
