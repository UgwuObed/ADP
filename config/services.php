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
    

    'zeptomail' => [
        'api_key' => env('ZEPTO_MAIL_API_KEY'),
        'sender_email' => env('ZEPTO_MAIL_SENDER_EMAIL'),
        'sender_name' => env('ZEPTO_MAIL_SENDER_NAME'),
        'otp_template_key' => env('ZEPTO_MAIL_OTP_TEMPLATE_KEY'),
        'password_success_template_key' => env('ZEPTO_MAIL_PASSWORD_SUCCESS_TEMPLATE_KEY'),
        'support_email' => env('SUPPORT_EMAIL', 'support@adp.com'),
    ],


];
