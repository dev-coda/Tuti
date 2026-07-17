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
        'base_url' => env('COORDINADORA_BASE_URL', 'https://api-test.coordinadora.tech'),
        'oauth_url' => env('COORDINADORA_OAUTH_URL', 'https://api-test.coordinadora.tech/oauth/token'),
        'guides_path' => env('COORDINADORA_GUIDES_PATH', '/guias'),
        'key' => env('COORDINADORA_KEY'),
        'secret' => env('COORDINADORA_SECRET'),
        'id_proceso' => env('COORDINADORA_ID_PROCESO'),

        // Commercial agreement identifiers (see docs/COORDINADORA/API Cotizador Nacional).
        'nit' => env('COORDINADORA_NIT', env('COORDINADORA_TRACKING_NIT')),
        'div' => env('COORDINADORA_DIV', '01'),
        'cuenta' => env('COORDINADORA_CUENTA', '1'),
        'producto' => env('COORDINADORA_PRODUCTO', '0'),
        'nivel_servicio' => env('COORDINADORA_NIVEL_SERVICIO', ''),
        'tipo_cuenta' => env('COORDINADORA_TIPO_CUENTA', 1),
        'usuario' => env('COORDINADORA_USUARIO'),

        // Dispatch origin. DANE code in 8-digit Coordinadora format (e.g. Medellín 05001000).
        'origin_dane' => env('COORDINADORA_ORIGIN_DANE'),
        'origin_name' => env('COORDINADORA_ORIGIN_NAME', 'Tronex'),
        'origin_address' => env('COORDINADORA_ORIGIN_ADDRESS'),
        'origin_phone' => env('COORDINADORA_ORIGIN_PHONE'),

        /** When true, hides Envío 48h / Coordinadora everywhere regardless of admin Setting */
        'express_48h_disabled' => filter_var(env('COORDINADORA_EXPRESS_48H_DISABLED', false), FILTER_VALIDATE_BOOL),
    ],

    /**
     * FV (sales order) creation in Dynamics 365 F&O via the CreateSalesOrder
     * SOAP webservice. See docs/fv.pdf. Endpoint defaults to
     * MICROSOFT_RESOURCE_URL + /soap/services/DYNPRODWSSalesForceGroup.
     */
    'fv' => [
        'endpoint' => env('FV_SOAP_ENDPOINT'),
        'soap_action' => env('FV_SOAP_ACTION', 'http://tempuri.org/DWSSalesForce/CreateSalesOrder'),
        'company' => env('FV_COMPANY', 'TRX'),
        'origen_venta' => env('FV_ORIGEN_VENTA', 'Tuti'),
        'order_type' => env('FV_ORDER_TYPE', 'PDVTA'),
        'doc_type' => env('FV_DOC_TYPE', 'Factura'),
        'approval' => env('FV_APPROVAL', 'YES'),
        'delivery_mode' => env('FV_DELIVERY_MODE', 'TERRESTRE'),
        'business_unit' => env('FV_BUSINESS_UNIT', '01'),
        'cost_center' => env('FV_COST_CENTER', '1'),
        'location_invoice' => env('FV_LOCATION_INVOICE', 'HHMEDELLIN'),
        'num_sequence_group' => env('FV_NUM_SEQUENCE_GROUP', 'HHMEDELLIN'),
        'default_warehouse' => env('FV_DEFAULT_WAREHOUSE'),
        // Conditional Dynamics dimensions (drive, resource, supervisor, vendor)
        'drive' => env('FV_DRIVE'),
        'resource' => env('FV_RESOURCE'),
        'supervisor' => env('FV_SUPERVISOR'),
        'vendor' => env('FV_VENDOR'),
    ],

];
