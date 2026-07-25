<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\User;
use Tests\TestCase;

class CentralUserTest extends TestCase
{
    public function test_is_admin_cannot_be_assigned_through_regular_mass_assignment(): void
    {
        $user = new User;
        $user->fill([
            'name' => 'Usuário',
            'email' => 'usuario@example.com',
            'password' => 'password',
            'is_admin' => true,
        ]);

        self::assertFalse($user->isFillable('is_admin'));
        self::assertNull($user->getAttribute('is_admin'));

        $user->forceFill(['is_admin' => 1]);

        self::assertTrue($user->is_admin);
    }
}
