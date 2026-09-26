<?php

namespace Tests\Feature\Api;

use App\Enums\ActivityAction;
use App\Enums\InstallmentStatus;
use App\Enums\UserRole;
use App\Models\Policy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyStatusApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsRole(UserRole::Broker);
    }

    public function test_issuing_a_quote_generates_installments(): void
    {
        $policy = Policy::factory()->quote()->create(['premium' => '1200.00', 'installments_count' => 4]);

        $this->patchJson("/api/policies/{$policy->id}/status", ['status' => 'pending'])
            ->assertOk()
            ->assertJsonPath('data.status.value', 'pending')
            ->assertJsonCount(4, 'data.installments');

        $this->patchJson("/api/policies/{$policy->id}/status", ['status' => 'active'])->assertOk();

        $this->assertSame(4, $policy->installments()->count());
    }

    public function test_invalid_transitions_are_rejected_with_the_enum_rules(): void
    {
        $quote = Policy::factory()->quote()->create();
        $cancelled = Policy::factory()->cancelled()->create();

        $this->patchJson("/api/policies/{$quote->id}/status", ['status' => 'active'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'Não é possível alterar o status de Cotação para Ativa.');

        $this->patchJson("/api/policies/{$cancelled->id}/status", ['status' => 'active'])->assertUnprocessable();
        $this->patchJson("/api/policies/{$quote->id}/status", ['status' => 'unknown'])->assertJsonValidationErrors('status');
    }

    public function test_cancelling_cancels_pending_installments_and_keeps_paid_ones(): void
    {
        $policy = Policy::factory()->active()->withInstallments()->create(['installments_count' => 3]);
        $policy->installments()->first()->registerPayment(today());

        $this->patchJson("/api/policies/{$policy->id}/status", ['status' => 'cancelled'])->assertOk();

        $this->assertSame(
            [InstallmentStatus::Paid, InstallmentStatus::Cancelled, InstallmentStatus::Cancelled],
            $policy->installments()->pluck('status')->all(),
        );
    }

    public function test_suspending_and_reactivating(): void
    {
        $policy = Policy::factory()->active()->create();

        $this->patchJson("/api/policies/{$policy->id}/status", ['status' => 'suspended'])->assertOk();
        $this->patchJson("/api/policies/{$policy->id}/status", ['status' => 'active'])->assertOk();
    }

    public function test_expiring_requires_the_term_to_have_ended(): void
    {
        $current = Policy::factory()->active()->create();
        $ended = Policy::factory()->active()->create(['starts_on' => today()->subYear()->subDay(), 'ends_on' => today()->subDay()]);

        $this->patchJson("/api/policies/{$current->id}/status", ['status' => 'expired'])->assertUnprocessable();
        $this->patchJson("/api/policies/{$ended->id}/status", ['status' => 'expired'])->assertOk();
    }

    public function test_status_change_is_audited_with_the_user(): void
    {
        $policy = Policy::factory()->active()->create();

        $this->patchJson("/api/policies/{$policy->id}/status", ['status' => 'suspended'])->assertOk();

        $log = $policy->activities()->where('action', ActivityAction::StatusChanged)->sole();
        $this->assertSame(auth()->id(), $log->user_id);
        $this->assertEquals(['old' => ['status' => 'active'], 'new' => ['status' => 'suspended']], $log->changes);
    }
}
