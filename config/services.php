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
        'team_invitation_template_key' => env('ZEPTOMAIL_TEAM_INVITATION_TEMPLATE_KEY', 'team_invitation'),
        'welcome_template_key' => env('ZEPTOMAIL_WELCOME_TEMPLATE_KEY', 'welcome'),
        'support_email' => env('SUPPORT_EMAIL', 'support@adp.com'),
    ],

    // 'vfd' => [
    //   //  'base_url' => env('VFD_BASE_URL', 'https://api-apps.vfdbank.systems/vtech-wallet/api/v2/wallet2'),
    //     'base_url' => env('VFD_BASE_URL', 'https://api-devapps.vfdbank.systems/vtech-wallet/api/v2/wallet2'),
    //     'access_token' => env('VFD_ACCESS_TOKEN'),
    // ],

    'vfd' => [
        'access_token' => env('VFD_ACCESS_TOKEN'),
        'base_url' => env('VFD_BASE_URL', 'https://api-apps.vfdbank.systems/vtech-wallet/api/v1/wallet2'),
    ],

    'vtu' => [
        'base_url' => env('VTU_BASE_URL'),
        'api_key' => env('VTU_API_KEY'),
     ],

    'topupbox' => [
        'base_url' => env('TOPUPBOX_BASE_URL', 'https://api.topupbox.com'),
        'access_token' => env('TOPUPBOX_ACCESS_TOKEN'),
      ],


];
