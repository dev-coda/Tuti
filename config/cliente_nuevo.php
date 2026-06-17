<?php

return [
    'base_url' => env('CLIENTE_NUEVO_BASE_URL', 'http://tronexdesarrollo.amovil.net/clienteConectat'),
    'token' => env('CLIENTE_NUEVO_TOKEN', ''),
    'auth' => [
        'url' => env('CLIENTE_NUEVO_AUTH_URL'),
        'username' => env('CLIENTE_NUEVO_AUTH_USERNAME'),
        'password' => env('CLIENTE_NUEVO_AUTH_PASSWORD'),
        'username_field' => env('CLIENTE_NUEVO_AUTH_USERNAME_FIELD', 'username'),
        'password_field' => env('CLIENTE_NUEVO_AUTH_PASSWORD_FIELD', 'password'),
        'token_field' => env('CLIENTE_NUEVO_AUTH_TOKEN_FIELD', 'token'),
        'ttl_seconds' => (int) env('CLIENTE_NUEVO_AUTH_TTL_SECONDS', 3300),
    ],
];
