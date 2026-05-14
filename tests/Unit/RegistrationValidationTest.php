<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RegistrationValidationTest extends TestCase
{
    public function testValidRegistrationInputIsNormalized(): void
    {
        $result = validateRegistrationInput('  NewUser  ', '  USER@Example.com ', 'StrongPass1');

        $this->assertTrue($result['success']);
        $this->assertSame('NewUser', $result['username']);
        $this->assertSame('user@example.com', $result['email']);
        $this->assertSame('StrongPass1', $result['password']);
    }

    public function testRejectsShortUsername(): void
    {
        $result = validateRegistrationInput('ab', 'user@example.com', 'StrongPass1');

        $this->assertFalse($result['success']);
        $this->assertSame('Username deve essere tra 3 e 50 caratteri.', $result['message']);
    }

    public function testRejectsWeakPassword(): void
    {
        $result = validateRegistrationInput('ValidUser', 'user@example.com', 'weakpass');

        $this->assertFalse($result['success']);
        $this->assertSame('La password deve contenere almeno una maiuscola e un numero.', $result['message']);
    }
}