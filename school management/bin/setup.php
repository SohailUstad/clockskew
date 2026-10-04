<?php

declare(strict_types=1);

use App\Database;

$config = require dirname(__DIR__) . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This setup command is CLI-only.\n");
    exit(1);
}

$username = $argv[1] ?? 'admin';
$password = $argv[2] ?? '';
if (strlen($password) < 8) {
    fwrite(STDERR, "Usage: php bin/setup.php <admin-username> <admin-password-at-least-8-chars>\n");
    exit(1);
}

$server = Database::connect($config['db'], true);
$database = str_replace('`', '``', $config['db']['name']);
$server->exec("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$mysql = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'mysql' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'mysql.exe';
$schema = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql';
$command = sprintf(
    '"%s" --host=%s --port=%d --user=%s --password=%s %s < "%s"',
    $mysql,
    escapeshellarg($config['db']['host']),
    $config['db']['port'],
    escapeshellarg($config['db']['username']),
    escapeshellarg($config['db']['password']),
    escapeshellarg($config['db']['name']),
    $schema
);
passthru($command, $exitCode);
if ($exitCode !== 0) {
    fwrite(STDERR, "Schema import failed.\n");
    exit($exitCode);
}

$db = Database::connect($config['db']);
$db->beginTransaction();
try {
    $statement = $db->prepare(
        'INSERT INTO users (username, password_hash, is_active) VALUES (?, ?, 1) '
        . 'ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), is_active = 1'
    );
    $statement->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
    $userIdStatement = $db->prepare('SELECT id FROM users WHERE username = ?');
    $userIdStatement->execute([$username]);
    $userId = (int) $userIdStatement->fetchColumn();
    $roleStatement = $db->prepare(
        "INSERT IGNORE INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE name = 'admin'"
    );
    $roleStatement->execute([$userId]);
    $db->commit();
} catch (Throwable $exception) {
    $db->rollBack();
    throw $exception;
}

fwrite(STDOUT, "Database initialized. Admin user: {$username}\n");
