<?php

return [
    'storage' => [
        'disk' => env('KYC_STORAGE_DISK', 's3'),
        'path' => env('KYC_STORAGE_PATH', 'kyc'),
    ],

    'file_limits' => [
        'document' => [
            'max_size_mb' => 5,
            'allowed_types' => ['pdf', 'jpg', 'jpeg', 'png'],
            'mime_types' => [
                'application/pdf',
                'image/jpeg',
                'image/jpg', 
                'image/png'
            ],
        ],
        'signature' => [
            'max_size_mb' => 2,
            'allowed_types' => ['jpg', 'jpeg', 'png'],
            'mime_types' => [
                'image/jpeg',
                'image/jpg',
                'image/png'
            ],
        ],
    ],

    'document_types' => [
        'business_certificate' => 'Business Certificate',
        'tax_certificate' => 'Tax Certificate', 
        // 'incorporation_certificate' => 'Certificate of Incorporation',
        // 'utility_bill' => 'Utility Bill',
    ],

  
    's3_optimization' => [
        'storage_class' => 'STANDARD_IA', 
        'lifecycle_rules' => [
            'transition_to_glacier_after_days' => 30,
            // 'delete_after_days' => 365, 
        ],
    ],
];