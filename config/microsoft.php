<?php

return [
    'url_token' => env('MICROSOFT_URL_TOKEN'),
    'client_id' => env('MICROSOFT_CLIENT_ID'),
    'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
    'resource' => env('MICROSOFT_RESOURCE_URL'),
    /** Log full getRuteros SOAP detail rows (verbose; may contain PII). Default off. */
    'log_rutero_soap_payload' => filter_var(env('RUTERO_LOG_SOAP_PAYLOAD', false), FILTER_VALIDATE_BOOLEAN),
];