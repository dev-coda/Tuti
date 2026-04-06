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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'coordinadora' => [
        'base_url' => env('COORDINADORA_BASE_URL', 'https://sandbox.coordinadora.com/api/v1'),
        'oauth_url' => env('COORDINADORA_OAUTH_URL', 'https://sandbox.coordinadora.com/oauth/token'),
        'key' => env('COORDINADORA_KEY'),
        'secret' => env('COORDINADORA_SECRET'),
        'id_proceso' => env('COORDINADORA_ID_PROCESO'),
    ],

    'fv_mock' => [
        'endpoint' => env('FV_MOCK_ENDPOINT', '/api/internal/fv-mock'),
        'token' => env('FV_MOCK_TOKEN', 'fv-mock-local-token'),
    ],

];
