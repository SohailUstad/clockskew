<?php

declare(strict_types=1);

namespace App;

final class Jwt
{
    public function __construct(
        private readonly string $secret,
        private readonly string $issuer,
        private readonly int $ttlSeconds
    ) {
        if (strlen($secret) < 32) {
            throw new \RuntimeException('JWT secret must be at least 32 characters. Configure config/local.php.');
        }
    }

    public function issue(int $userId, string $username, string $role): string
    {
        $now = time();
        $header = $this->encodePart(['alg' => 'HS256', 'typ' => 'JWT']);
        $payload = $this->encodePart([
            'iss' => $this->issuer,
            'sub' => (string) $userId,
            'username' => $username,
            'role' => $role,
            'iat' => $now,
            'exp' => $now + $this->ttlSeconds,
        ]);
        $signature = $this->base64Url(hash_hmac('sha256', $header . '.' . $payload, $this->secret, true));

        return $header . '.' . $payload . '.' . $signature;
    }

    public function verify(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new ApiException(401, 'Invalid authentication token.');
        }

        [$headerPart, $payloadPart, $signature] = $parts;
        $expected = $this->base64Url(hash_hmac('sha256', $headerPart . '.' . $payloadPart, $this->secret, true));
        if (!hash_equals($expected, $signature)) {
            throw new ApiException(401, 'Invalid authentication token.');
        }

        $header = $this->decodePart($headerPart);
        $claims = $this->decodePart($payloadPart);
        if (($header['alg'] ?? null) !== 'HS256' || ($claims['iss'] ?? null) !== $this->issuer) {
            throw new ApiException(401, 'Invalid authentication token.');
        }
        if (!isset($claims['sub'], $claims['role'], $claims['exp']) || time() >= (int) $claims['exp']) {
            throw new ApiException(401, 'Authentication token has expired or is invalid.');
        }

        return $claims;
    }

    private function encodePart(array $data): string
    {
        return $this->base64Url((string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function decodePart(string $part): array
    {
        $decoded = base64_decode(strtr($part, '-_', '+/') . str_repeat('=', (4 - strlen($part) % 4) % 4), true);
        if ($decoded === false) {
            throw new ApiException(401, 'Invalid authentication token.');
        }

        try {
            $data = json_decode($decoded, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ApiException(401, 'Invalid authentication token.');
        }

        if (!is_array($data)) {
            throw new ApiException(401, 'Invalid authentication token.');
        }

        return $data;
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

