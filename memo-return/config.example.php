<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    http_response_code(404);
    exit;
}

/**
 * Copy to config.local.php on each site. Do not commit the copy.
 * The same folder runs on the testing site and the live site.
 */
return [
    'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=YOUR_DATABASE;charset=utf8mb4',
    'user' => 'YOUR_DATABASE_USER',
    'password' => 'YOUR_DATABASE_PASSWORD',
    'gate_password' => 'choose-a-long-password',
    'public_url' => '',
    'shop' => [
        'name' => 'টেস্টিং',
        'tagline' => 'স্পেয়ার পার্টস ব্যবসা ও ইঞ্জিনিয়ারিং ওয়ার্কশপ',
        'phone' => '',
        'address' => '',
        'logo' => '/assets/img/ma-logo.png',
    ],
];
