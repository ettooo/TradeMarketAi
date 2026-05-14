<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PermissionTest extends TestCase
{
    public function testPermissionLookupIsStrict(): void
    {
        $permissions = ['view_dashboard', 'manage_portfolio'];

        $this->assertTrue(userHasPermission($permissions, 'manage_portfolio'));
        $this->assertFalse(userHasPermission($permissions, 'view_market_data'));
        $this->assertFalse(userHasPermission($permissions, 'manage_portfolio '));
    }
}