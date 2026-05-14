<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class JwtTest extends TestCase
{
    public function testJwtCreationAndVerificationRoundTrip(): void
    {
        $before = time();
        $token = jwtCreate([
            'sub' => 42,
            'name' => 'Test User',
            'role' => 'premium',
        ]);
        $after = time();

        $payload = jwtVerify($token);

        $this->assertSame(42, (int)$payload['sub']);
        $this->assertSame('Test User', $payload['name']);
        $this->assertSame('premium', $payload['role']);
        $this->assertSame(JWT_ISSUER, $payload['iss']);
        $this->assertSame(JWT_ACCESS_TTL, (int)$payload['exp'] - (int)$payload['iat']);
        $this->assertGreaterThanOrEqual($before, (int)$payload['iat']);
        $this->assertLessThanOrEqual($after, (int)$payload['iat']);
    }

    public function testJwtVerificationRejectsMalformedToken(): void
    {
        $this->expectException(InvalidArgumentException::class);

        jwtVerify('not-a-token');
    }

    public function testJwtVerificationRejectsTamperedToken(): void
    {
        $token = jwtCreate([
            'sub' => 7,
            'name' => 'User',
            'role' => 'free',
        ]);

        $parts = explode('.', $token);
        $parts[2] = strrev($parts[2]);

        $this->expectException(RuntimeException::class);
        jwtVerify(implode('.', $parts));
    }
}