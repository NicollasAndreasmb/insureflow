<?php

namespace Tests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * As requisições de teste se identificam como vindas da SPA (Referer no domínio
 * stateful), para que o Sanctum inicie a sessão exatamente como em produção:
 * login, logout e CSRF passam pelo mesmo pipeline de middleware.
 */
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Referer', 'http://localhost');
    }

    protected function actingAsRole(UserRole $role): User
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user);

        return $user;
    }
}
