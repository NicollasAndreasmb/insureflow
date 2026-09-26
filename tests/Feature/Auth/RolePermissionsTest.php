<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Documenta a matriz de perfis tal como exposta ao frontend em /api/user.
 * Os testes de cada módulo verificam que os endpoints aplicam a mesma matriz.
 */
class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public static function roles(): array
    {
        return [
            'admin' => [UserRole::Admin, [
                'clients.create' => true, 'clients.delete' => true,
                'insurers.manage' => true, 'insurance_products.manage' => true,
                'policies.manage' => true, 'policies.delete' => true, 'coverages.manage' => true,
                'claims.manage' => true, 'claims.delete' => true, 'users.manage' => true,
            ]],
            'broker' => [UserRole::Broker, [
                'clients.create' => true, 'clients.delete' => true,
                'insurers.manage' => false, 'insurance_products.manage' => false,
                'policies.manage' => true, 'policies.delete' => false, 'coverages.manage' => true,
                'claims.manage' => true, 'claims.delete' => false, 'users.manage' => false,
            ]],
            'assistant' => [UserRole::Assistant, [
                'clients.create' => true, 'clients.delete' => false,
                'insurers.manage' => false, 'insurance_products.manage' => false,
                'policies.manage' => false, 'policies.delete' => false, 'coverages.manage' => false,
                'claims.manage' => true, 'claims.delete' => false, 'users.manage' => false,
            ]],
        ];
    }

    #[DataProvider('roles')]
    public function test_current_user_exposes_permissions_of_the_role(UserRole $role, array $expected): void
    {
        $this->actingAsRole($role);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.permissions', $expected);
    }
}
