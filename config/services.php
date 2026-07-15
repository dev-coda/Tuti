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
