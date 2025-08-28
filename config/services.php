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
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],
    
'paystack' => [
    'secret_key' => env('PAYSTACK_SECRET_KEY'),
    'public_key' => env('PAYSTACK_PUBLIC_KEY'),
    'payment_url' => env('PAYSTACK_PAYMENT_URL', 'https://api.paystack.co'),
],

'payaza' => [
        'api_key' => env('PAYAZA_API_KEY'), 
        'secret_key' => env('PAYAZA_SECRET_KEY'), 
        'base_url' => env('PAYAZA_BASE_URL', 'https://api.payaza.africa/live'),
        'default_bvn' => env('PAYAZA_DEFAULT_BVN', '22123456789'), 
        'default_bank_code' => env('PAYAZA_BANK_CODE', '117'), 
        'tenant_id' => env('PAYAZA_TENANT_ID', 'test'),
],

'zeptomail' => [
    'api_key' => env('ZEPTO_API_KEY'),
    'sender_email' => env('ZEPTO_SENDER_EMAIL'),
    'sender_name' => env('ZEPTO_SENDER_NAME'),
    'templates' => [
        'registration' => '2d6f.12bcca75ac38de41.k1.19356f80-285c-11f0-9934-86f7e6aa0425.19697cefe78',
        'order_confirmation' => '2d6f.12bcca75ac38de41.k1.dc751dc0-2dec-11f0-8ad3-86f7e6aa0425.196bc48519c',
        'new_order' => '2d6f.12bcca75ac38de41.k1.2cc771a0-2dee-11f0-8ad3-86f7e6aa0425.196bc50edba',
        'order_status_update' => env('ZEPTOMAIL_TEMPLATE_ORDER_STATUS_UPDATE'),
        'password_reset' => '2d6f.12bcca75ac38de41.k1.36e9f710-59b9-11f0-9a6f-522b4d8f9316.197db519901',
        'password_changed' => '2d6f.12bcca75ac38de41.k1.8c429140-59b9-11f0-9a6f-522b4d8f9316.197db53c854',
    ],
],


];
