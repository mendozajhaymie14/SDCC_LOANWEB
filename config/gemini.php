<?php

declare(strict_types=1);

return [

    'api_key' => env('GEMINI_API_KEY'),

    'base_url' => env('GEMINI_BASE_URL'),

    'model' => env('GEMINI_MODEL', 'gemini-2.0-flash-exp'),

    'request_timeout' => env('GEMINI_REQUEST_TIMEOUT', 30),
];