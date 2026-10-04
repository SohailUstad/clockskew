<?php

declare(strict_types=1);

namespace App;

final class Request
{
    public readonly string $method;
    public readonly string $path;
    public readonly array $query;
    private ?array $body = null;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $apiPosition = strpos($path, '/api');
        $this->path = rtrim($apiPosition === false ? $path : substr($path, $apiPosition), '/') ?: '/';
        $this->query = $_GET;
    }

    public function json(): array
    {
        if ($this->body !== null) {
            return $this->body;
        }

        $raw = file_get_contents('php://input') ?: '';
        if ($raw === '') {
            return $this->body = [];
        }

        try {
            $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ApiException(400, 'Request body must contain valid JSON.');
        }
        if (!is_array($data)) {
            throw new ApiException(400, 'Request body must be a JSON object.');
        }

        return $this->body = $data;
    }

    public function bearerToken(): string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer\s+(\S+)$/i', trim($header), $matches)) {
            throw new ApiException(401, 'A Bearer authentication token is required.');
        }

        return $matches[1];
    }
}

