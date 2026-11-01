<?php

namespace OktaaxExamples\WebSocket;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Oktaax\Exception\HttpException;
use Oktaax\Http\Request;

/** Replace these local demo accounts with your application's real authentication service. */
final class DemoAuth
{
    public const COOKIE = 'oktaax_ws_demo';
    private const ISSUER = 'oktaax-websocket-example';
    private const AUDIENCE = 'oktaax-websocket-client';
    private const USERS = [
        'alice' => [
            'password' => 'alice-demo',
            'name' => 'Alice',
            'role' => 'member',
            'rooms' => ['lobby', 'engineering'],
        ],
        'bob' => [
            'password' => 'bob-demo',
            'name' => 'Bob',
            'role' => 'member',
            'rooms' => ['lobby', 'design'],
        ],
        'admin' => [
            'password' => 'admin-demo',
            'name' => 'Admin',
            'role' => 'admin',
            'rooms' => ['lobby', 'engineering', 'design', 'staff'],
        ],
    ];

    /** @param list<string> $origins */
    public function __construct(
        private string $secret,
        public readonly array $origins,
        public readonly int $sessionSeconds = 1200,
    ) {
        if (
            strlen($secret) < 32 ||
            $sessionSeconds < 60 ||
            $sessionSeconds > 3600
        ) {
            throw new \InvalidArgumentException(
                'Use a secret of at least 32 bytes and a session lifetime of 60–3600 seconds.',
            );
        }
        foreach ($origins as $origin) {
            $parts = parse_url($origin);
            if (
                $parts === false ||
                !in_array($parts['scheme'] ?? null, ['http', 'https'], true) ||
                !isset($parts['host']) ||
                isset($parts['user']) ||
                isset($parts['pass']) ||
                isset($parts['path']) ||
                isset($parts['query']) ||
                isset($parts['fragment'])
            ) {
                throw new \InvalidArgumentException(
                    'Origins must be exact http(s) origins without paths or credentials.',
                );
            }
        }
        if ($origins === []) {
            throw new \InvalidArgumentException(
                'At least one allowed origin is required.',
            );
        }
    }

    public function requireOrigin(Request $request): void
    {
        if (!in_array($request->header('origin'), $this->origins, true)) {
            throw new HttpException(403, 'Origin is not allowed.');
        }
    }

    /** @return array{token: string, identity: Identity} */
    public function login(string $username, string $password): array
    {
        $user = self::USERS[$username] ?? null;
        if ($user === null || !hash_equals($user['password'], $password)) {
            throw new HttpException(401, 'Invalid demo credentials.');
        }
        $now = time();
        $expires = $now + $this->sessionSeconds;
        return [
            'token' => JWT::encode(
                [
                    'iss' => self::ISSUER,
                    'aud' => self::AUDIENCE,
                    'sub' => $username,
                    'iat' => $now,
                    'exp' => $expires,
                ],
                $this->secret,
                'HS256',
            ),
            'identity' => $this->identity($username, $expires),
        ];
    }

    public function fromRequest(Request $request): ?Identity
    {
        $token = $request->cookie(self::COOKIE);
        if (!is_string($token) || $token === '') {
            return null;
        }
        try {
            $claims = JWT::decode($token, new Key($this->secret, 'HS256'));
            if (
                ($claims->iss ?? null) !== self::ISSUER ||
                ($claims->aud ?? null) !== self::AUDIENCE ||
                !is_string($claims->sub ?? null) ||
                !isset(self::USERS[$claims->sub]) ||
                !is_int($claims->exp ?? null) ||
                $claims->exp <= time()
            ) {
                return null;
            }
            return $this->identity($claims->sub, $claims->exp);
        } catch (\UnexpectedValueException | \DomainException) {
            return null;
        }
    }

    private function identity(string $id, int $expires): Identity
    {
        $user = self::USERS[$id];
        return new Identity(
            $id,
            $user['name'],
            $user['role'],
            $user['rooms'],
            $expires,
        );
    }
}
