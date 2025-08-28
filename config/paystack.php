<?php

return [
    /**
     * Public Key From Paystack Dashboard
     */
    'publicKey' => env('PAYSTACK_PUBLIC_KEY', 'pk_test_xxxxxxxxxxx'),

    /**
     * Secret Key From Paystack Dashboard
     */
    'secretKey' => env('PAYSTACK_SECRET_KEY', 'sk_test_xxxxxxxxxxx'),

    /**
     * Paystack Payment URL
     */
    'paymentUrl' => env('PAYSTACK_PAYMENT_URL', 'https://api.paystack.co'),

    /**
     * Optional email address of the merchant
     */
    'merchantEmail' => env('MERCHANT_EMAIL', 'merchant@example.com'),
];