<?php

declare(strict_types=1);

use App\Api;
use App\ApiException;
use App\Database;
use App\Jwt;
use App\Request;
use App\Response;

$config = require dirname(__DIR__) . '/bootstrap.php';

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && in_array($origin, $config['cors_origins'], true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Headers: Authorization, Content-Type');
    header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
}
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $db = Database::connect($config['db']);
    $jwt = new Jwt($config['jwt']['secret'], $config['jwt']['issuer'], $config['jwt']['ttl_seconds']);
    (new Api($db, $jwt, new Request()))->dispatch();
} catch (ApiException $exception) {
    Response::json(['error' => ['message' => $exception->getMessage(), 'details' => $exception->details]], $exception->status);
} catch (PDOException $exception) {
    error_log($exception->__toString());
    $apiException = Api::handleDatabaseException($exception);
    Response::json(['error' => ['message' => $apiException->getMessage(), 'details' => $apiException->details]], $apiException->status);
} catch (Throwable $exception) {
    error_log($exception->__toString());
    Response::json(['error' => ['message' => 'Internal server error.']], 500);
}
