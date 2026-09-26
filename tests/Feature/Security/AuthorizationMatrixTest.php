<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Models\Claim;
use App\Models\Client;
use App\Models\Coverage;
use App\Models\Installment;
use App\Models\InsuranceProduct;
use App\Models\Insurer;
use App\Models\Policy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Garante no backend a matriz de perfis para toda operação de escrita,
 * independentemente do que o frontend exibe. Cada caso só verifica se a
 * requisição foi barrada (403) ou não: regras de negócio e validação são
 * cobertas pelos testes de cada módulo.
 */
class AuthorizationMatrixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string, list<UserRole>}>
     */
    public static function writeOperations(): array
    {
        $everyone = [UserRole::Admin, UserRole::Broker, UserRole::Assistant];
        $portfolio = [UserRole::Admin, UserRole::Broker];
        $admin = [UserRole::Admin];

        return [
            'create client' => ['POST', '/api/clients', $everyone],
            'update client' => ['PUT', '/api/clients/{client}', $everyone],
            'delete client' => ['DELETE', '/api/clients/{client}', $portfolio],
            'create insurer' => ['POST', '/api/insurers', $admin],
            'update insurer' => ['PUT', '/api/insurers/{insurer}', $admin],
            'delete insurer' => ['DELETE', '/api/insurers/{insurer}', $admin],
            'create product' => ['POST', '/api/insurance-products', $admin],
            'update product' => ['PUT', '/api/insurance-products/{product}', $admin],
            'delete product' => ['DELETE', '/api/insurance-products/{product}', $admin],
            'create policy' => ['POST', '/api/policies', $portfolio],
            'update policy' => ['PATCH', '/api/policies/{policy}', $portfolio],
            'change policy status' => ['PATCH', '/api/policies/{policy}/status', $portfolio],
            'delete policy' => ['DELETE', '/api/policies/{policy}', $admin],
            'create coverage' => ['POST', '/api/policies/{policy}/coverages', $portfolio],
            'update coverage' => ['PUT', '/api/coverages/{coverage}', $portfolio],
            'delete coverage' => ['DELETE', '/api/coverages/{coverage}', $portfolio],
            'pay installment' => ['POST', '/api/installments/{installment}/payment', $everyone],
            'open claim' => ['POST', '/api/claims', $everyone],
            'update claim' => ['PATCH', '/api/claims/{claim}', $everyone],
            'change claim status' => ['PATCH', '/api/claims/{claim}/status', $everyone],
            'delete claim' => ['DELETE', '/api/claims/{claim}', $admin],
            'list users' => ['GET', '/api/users', $admin],
            'show user' => ['GET', '/api/users/{user}', $admin],
            'create user' => ['POST', '/api/users', $admin],
            'update user' => ['PUT', '/api/users/{user}', $admin],
            'delete user' => ['DELETE', '/api/users/{user}', $admin],
        ];
    }

    #[DataProvider('writeOperations')]
    public function test_operation_follows_the_role_matrix(string $method, string $uri, array $allowedRoles): void
    {
        foreach ([UserRole::Admin, UserRole::Broker, UserRole::Assistant] as $role) {
            $this->actingAsRole($role);
            $status = $this->json($method, $this->resolve($uri))->status();

            if (in_array($role, $allowedRoles, true)) {
                $this->assertNotSame(403, $status, "{$role->value} should be allowed to {$method} {$uri}");
            } else {
                $this->assertSame(403, $status, "{$role->value} should be forbidden to {$method} {$uri}");
            }
        }
    }

    /**
     * Cria registros novos a cada perfil, para que uma exclusão permitida a um
     * perfil não faça os seguintes receberem 404 em vez de 403.
     */
    private function resolve(string $uri): string
    {
        $policy = Policy::factory()->quote()->create();

        return strtr($uri, [
            '{client}' => Client::factory()->create()->id,
            '{insurer}' => Insurer::factory()->create()->id,
            '{product}' => InsuranceProduct::factory()->create()->id,
            '{policy}' => $policy->id,
            '{coverage}' => Coverage::factory()->create(['policy_id' => $policy->id])->id,
            '{installment}' => Installment::factory()->create()->id,
            '{claim}' => Claim::factory()->create()->id,
            '{user}' => User::factory()->create()->id,
        ]);
    }

    public function test_audit_trail_cannot_be_written_or_deleted_through_the_api(): void
    {
        $this->actingAsRole(UserRole::Admin);
        $log = Client::factory()->create()->activities()->sole();

        $this->postJson('/api/activity-logs', ['action' => 'created'])->assertMethodNotAllowed();
        $this->deleteJson('/api/activity-logs')->assertMethodNotAllowed();
        $this->deleteJson("/api/activity-logs/{$log->id}")->assertNotFound();

        $this->assertModelExists($log);
    }
}
