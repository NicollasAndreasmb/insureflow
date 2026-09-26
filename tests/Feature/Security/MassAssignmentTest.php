<?php

namespace Tests\Feature\Security;

use App\Enums\ClaimStatus;
use App\Enums\InstallmentStatus;
use App\Enums\UserRole;
use App\Models\Coverage;
use App\Models\Installment;
use App\Models\Policy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Campos controlados pelo sistema (status, protocolo, comissão, vínculos) não
 * podem ser definidos pelo cliente da API: ou são recusados explicitamente
 * (422) ou ignorados por não fazerem parte dos dados validados.
 */
class MassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_claim_status_protocol_and_amounts_are_not_client_controlled(): void
    {
        $this->actingAsRole(UserRole::Assistant);
        $policy = Policy::factory()->active()->create(['starts_on' => today()->subMonth(), 'ends_on' => today()->addMonths(11)]);

        $response = $this->postJson('/api/claims', [
            'policy_id' => $policy->id,
            'occurred_on' => today()->subDay()->toDateString(),
            'description' => 'Colisão.',
            'estimated_amount' => '1000.00',
            'status' => 'approved',
            'approved_amount' => '999999.00',
            'protocol' => 'SIN-1999-000001',
        ])->assertCreated();

        $this->assertSame(ClaimStatus::Open->value, $response->json('data.status.value'));
        $this->assertNull($response->json('data.approved_amount'));
        $this->assertNotSame('SIN-1999-000001', $response->json('data.protocol'));
    }

    public function test_policy_commission_amount_and_status_cannot_be_set_directly(): void
    {
        $this->actingAsRole(UserRole::Broker);
        $policy = Policy::factory()->quote()->create(['premium' => '1000.00', 'commission_rate' => '10.00']);

        $this->patchJson("/api/policies/{$policy->id}", ['commission_amount' => '999.00', 'notes' => 'x'])
            ->assertOk()
            ->assertJsonPath('data.commission_amount', '100.00');

        $this->patchJson("/api/policies/{$policy->id}", ['status' => 'active'])->assertUnprocessable();
    }

    public function test_coverage_cannot_be_moved_to_another_policy(): void
    {
        $this->actingAsRole(UserRole::Broker);
        $coverage = Coverage::factory()->create();
        $otherPolicy = Policy::factory()->create();

        $this->putJson("/api/coverages/{$coverage->id}", [
            'name' => 'Vendaval',
            'coverage_limit' => '100.00',
            'policy_id' => $otherPolicy->id,
        ])->assertOk();

        $this->assertNotSame($otherPolicy->id, $coverage->fresh()->policy_id);
    }

    public function test_payment_cannot_choose_the_resulting_status(): void
    {
        $this->actingAsRole(UserRole::Assistant);
        $installment = Installment::factory()->create();

        $this->postJson("/api/installments/{$installment->id}/payment", ['paid_on' => today()->toDateString(), 'status' => 'cancelled'])
            ->assertOk();

        $this->assertSame(InstallmentStatus::Paid, $installment->fresh()->status);
    }

    public function test_users_cannot_escalate_their_own_role_or_change_another_id(): void
    {
        $broker = $this->actingAsRole(UserRole::Broker);
        $admin = User::factory()->admin()->create();

        $this->putJson("/api/users/{$broker->id}", ['name' => 'x', 'email' => $broker->email, 'role' => 'admin'])->assertForbidden();
        $this->assertSame(UserRole::Broker, $broker->fresh()->role);

        $this->actingAs($admin);
        $this->putJson("/api/users/{$broker->id}", [
            'id' => $admin->id,
            'name' => 'Bruno',
            'email' => $broker->email,
            'role' => 'broker',
        ])->assertOk()->assertJsonPath('data.id', $broker->id);
    }
}
