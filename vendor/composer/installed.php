<?php return array(
    'root' => array(
        'name' => 'payment-gateway/php-backend',
        'pretty_version' => 'dev-main',
        'version' => 'dev-main',
        'reference' => '4e16f7a197028129af4f40adb18d3ef7644b13b7',
        'type' => 'project',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => true,
    ),
    'versions' => array(
        'payment-gateway/php-backend' => array(
            'pretty_version' => 'dev-main',
            'version' => 'dev-main',
            'reference' => '4e16f7a197028129af4f40adb18d3ef7644b13b7',
            'type' => 'project',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'stripe/stripe-php' => array(
            'pretty_version' => 'v13.18.0',
            'version' => '13.18.0.0',
            'reference' => '02abb043b103766f4ed920642ae56ffdc58c7467',
            'type' => 'library',
            'install_path' => __DIR__ . '/../stripe/stripe-php',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
