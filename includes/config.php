<?php
if (!defined('ABSPATH')) {
    exit;
}

return array(
    // API Configuration
    'api' => array(
        'base_url' => 'https://api.lapinopay.com/api',
        'checkout_url' => 'https://www.lapinopay.com/process-payment/wc/',
        'timeout' => 30,
        'token_validation_endpoint' => 'auth/check_validation',
    ),

    // Payment Settings
    'payment' => array(
        'provider' => 'lapinopay',
        'minimum_amount' => 20,
    ),

    // REST API
    'rest' => array(
        'namespace' => 'lapinopay/v1',
        'route' => '/payment-callback/',
        'allowed_origins' => array(
            'https://www.lapinopay.com', // Development server
            'http://localhost:8080', // Local WordPress server
            'https://lapinopay.com', // Production server
            'https://api.lapinopay.com', // Production server
        ),
    ),
); 