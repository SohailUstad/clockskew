<?php

declare(strict_types=1);

$config = [
    'timezone' => 'Asia/Kolkata',
    // 'db' => [
    //     'host' => getenv('DB_HOST') ?: '127.0.0.1',
    //     'port' => (int) (getenv('DB_PORT') ?: 3306),
    //     'name' => getenv('DB_NAME') ?: 'school_management',
    //     'username' => getenv('DB_USERNAME') ?: 'root',
    //     'password' => getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '0000',
    // ],
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'u829943449_clockskew',
        'username' => 'u829943449_clockskew',
        'password' => 'h9E>iVB4gl',
    ],
    'jwt' => [
        'issuer' => getenv('JWT_ISSUER') ?: 'school-management-api',
        'secret' => getenv('JWT_SECRET') ?: '',
        'ttl_seconds' => (int) (getenv('JWT_TTL_SECONDS') ?: 3600),
    ],
    'cors_origins' => array_values(array_filter(array_map(
        'trim',
        // explode(',', getenv('CORS_ORIGINS') ?: 'http://localhost,http://127.0.0.1')
        explode(',', getenv('CORS_ORIGINS') ?: '*')
    ))),
];

$localFile = __DIR__ . '/local.php';
if (is_file($localFile)) {
    $config = array_replace_recursive($config, require $localFile);
}

return $config;
