<?php

namespace App\Security\Infrastructure\Security;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class JwtService
{
    private string $secret;

    public function __construct(
        #[Autowire('%jwt.secret%')] string $secret,
        #[Autowire('%jwt.ttl%')] private readonly int $ttl,
    ) {
        if ($secret === '') {
            throw new \RuntimeException('JWT_SECRET doit être configuré.');
        }
        // HS256 exige au moins 256 bits.
        $this->secret = strlen($secret) < 32 ? hash('sha256', $secret) : $secret;
    }

    /** @param array<string,mixed> $claims */
    public function generate(array $claims): string
    {
        $now = time();
        return JWT::encode([...$claims, 'iat' => $now, 'exp' => $now + $this->ttl, 'iss' => 'colloque-api'], $this->secret, 'HS256');
    }

    /** @return array<string,mixed>|null */
    public function validate(string $token): ?array
    {
        try {
            return (array) JWT::decode($token, new Key($this->secret, 'HS256'));
        } catch (\Throwable) {
            return null;
        }
    }
}
