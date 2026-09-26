<?php

namespace Tests\Feature\Api;

use App\Enums\InstallmentStatus;
use App\Enums\PolicyStatus;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Installment;
use App\Models\InsuranceProduct;
use App\Models\Insurer;
use App\Models\Policy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyApiTest extends TestCase
{
    use RefreshDatabase;

    private User $broker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->broker = $this->actingAsRole(UserRole::Broker);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'number' => '01.10.0001234',
            'client_id' => Client::factory()->create()->id,
            'insurer_id' => Insurer::factory()->create()->id,
            'insurance_product_id' => InsuranceProduct::factory()->create()->id,
            'starts_on' => today()->toDateString(),
            'ends_on' => today()->addYear()->toDateString(),
            'premium' => '1000.00',
            'deductible' => '2500.00',
            'commission_rate' => '12.5',
            'payment_method' => 'credit_card',
            'installments_count' => 3,
            ...$overrides,
        ];
    }

    public function test_creates_a_quote_without_installments(): void
    {
        $response = $this->postJson('/api/policies', $this->payload([
            'coverages' => [
                ['name' => 'Colisão', 'coverage_limit' => '80000.00'],
                ['name' => 'Terceiros', 'coverage_limit' => '100000.00', 'additional_premium' => '120.00'],
            ],
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.status.value', 'quote')
            ->assertJsonPath('data.broker_id', $this->broker->id)
            ->assertJsonPath('data.commission_amount', '125.00')
            ->assertJsonCount(2, 'data.coverages')
            ->assertJsonCount(0, 'data.installments');
    }

    public function test_creating_an_active_policy_generates_installments_from_the_premium(): void
    {
        $response = $this->postJson('/api/policies', $this->payload(['status' => 'active']))->assertCreated();

        $this->assertSame(['333.34', '333.33', '333.33'], array_column($response->json('data.installments'), 'amount'));
        $this->assertSame(
            [today()->toDateString(), today()->addMonthNoOverflow()->toDateString(), today()->addMonthsNoOverflow(2)->toDateString()],
            array_column($response->json('data.installments'), 'due_date'),
        );
    }

    public function test_validates_policy_fields(): void
    {
        $this->postJson('/api/policies', $this->payload([
            'ends_on' => today()->subDay()->toDateString(),
            'premium' => '-10',
            'commission_rate' => '120',
            'installments_count' => 13,
            'payment_method' => 'cash',
            'status' => 'expired',
            'client_id' => Client::factory()->inactive()->create()->id,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'ends_on', 'premium', 'commission_rate', 'installments_count', 'payment_method', 'status', 'client_id',
            ]);
    }

    public function test_policy_number_is_unique_per_insurer(): void
    {
        $existing = Policy::factory()->create(['number' => '01.10.0001234']);

        $this->postJson('/api/policies', $this->payload(['insurer_id' => $existing->insurer_id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('number');

        $this->postJson('/api/policies', $this->payload())->assertCreated();
    }

    public function test_active_policy_requires_a_current_term(): void
    {
        $this->postJson('/api/policies', $this->payload([
            'status' => 'active',
            'starts_on' => today()->subYears(2)->toDateString(),
            'ends_on' => today()->subYear()->toDateString(),
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ends_on');
    }

    public function test_premium_too_low_for_the_installments_is_rejected(): void
    {
        $this->postJson('/api/policies', $this->payload(['status' => 'active', 'premium' => '0.05', 'installments_count' => 12]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('premium');

        $this->assertDatabaseCount('policies', 0);
    }

    public function test_updating_financial_fields_regenerates_unpaid_installments(): void
    {
        $policy = Policy::factory()->active()->withInstallments()->create(['premium' => '1000.00', 'installments_count' => 2]);

        $this->patchJson("/api/policies/{$policy->id}", ['premium' => '1500.00', 'installments_count' => 3, 'commission_rate' => '10'])
            ->assertOk()
            ->assertJsonPath('data.commission_amount', '150.00')
            ->assertJsonCount(3, 'data.installments');

        $this->assertSame('1500.00', number_format((float) $policy->installments()->sum('amount'), 2, '.', ''));
    }

    public function test_financial_fields_are_locked_after_a_payment(): void
    {
        $policy = Policy::factory()->active()->withInstallments()->create(['installments_count' => 2]);
        $policy->installments()->first()->registerPayment(today());

        $this->patchJson("/api/policies/{$policy->id}", ['premium' => '9999.00'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.premium.0', 'Não é possível alterar prêmio, parcelas ou início da vigência de uma apólice com parcelas pagas.');

        $this->patchJson("/api/policies/{$policy->id}", ['notes' => 'Endosso solicitado.'])->assertOk();
    }

    public function test_start_date_cannot_move_past_the_current_end_date(): void
    {
        $policy = Policy::factory()->quote()->create();

        $this->patchJson("/api/policies/{$policy->id}", ['starts_on' => $policy->ends_on->addDay()->toDateString()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_on');
    }

    public function test_final_policies_only_accept_notes(): void
    {
        $policy = Policy::factory()->expired()->create();

        $this->patchJson("/api/policies/{$policy->id}", ['premium' => '10.00'])->assertUnprocessable();
        $this->patchJson("/api/policies/{$policy->id}", ['notes' => 'Renovada em nova apólice.'])
            ->assertOk()
            ->assertJsonPath('data.notes', 'Renovada em nova apólice.');
    }

    public function test_status_cannot_be_changed_through_update(): void
    {
        $policy = Policy::factory()->quote()->create();

        $this->patchJson("/api/policies/{$policy->id}", ['status' => 'active'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_lists_policies_with_filters(): void
    {
        $client = Client::factory()->create(['name' => 'Transportadora Xylo']);
        Policy::factory()->active()->create([
            'client_id' => $client->id,
            'starts_on' => today()->subMonth(),
            'ends_on' => today()->subMonth()->addYear(),
        ]);
        Policy::factory()->expiringInDays(10)->create();
        Policy::factory()->quote()->create();

        $this->getJson('/api/policies?search=xylo')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/policies?status=quote')->assertJsonCount(1, 'data');
        $this->getJson('/api/policies?expiring_within_days=30')->assertJsonCount(1, 'data');
        $this->getJson('/api/policies?sort=-premium')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/policies?status=draft')->assertUnprocessable();
    }

    public function test_only_quotes_can_be_deleted_and_only_by_admins(): void
    {
        $quote = Policy::factory()->quote()->create();
        $active = Policy::factory()->active()->create();

        $this->deleteJson("/api/policies/{$quote->id}")->assertForbidden();

        $this->actingAsRole(UserRole::Admin);
        $this->deleteJson("/api/policies/{$active->id}")->assertUnprocessable();
        $this->deleteJson("/api/policies/{$quote->id}")->assertNoContent();
        $this->assertSoftDeleted($quote);
    }

    public function test_assistants_can_read_but_not_write_policies(): void
    {
        $policy = Policy::factory()->create();
        $this->actingAsRole(UserRole::Assistant);

        $this->getJson('/api/policies')->assertOk();
        $this->getJson("/api/policies/{$policy->id}")->assertOk();
        $this->postJson('/api/policies', $this->payload())->assertForbidden();
        $this->patchJson("/api/policies/{$policy->id}", ['notes' => 'x'])->assertForbidden();
        $this->patchJson("/api/policies/{$policy->id}/status", ['status' => 'cancelled'])->assertForbidden();
    }

    public function test_show_includes_the_policy_aggregate(): void
    {
        $policy = Policy::factory()->active()->withInstallments()->create(['installments_count' => 2]);

        $this->getJson("/api/policies/{$policy->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'client', 'insurer', 'product', 'broker', 'coverages', 'installments', 'claims',
                'commission_rate', 'commission_amount', 'payment_method' => ['value', 'label'],
            ]]);
    }

    public function test_overdue_installments_are_reported_with_derived_status(): void
    {
        $installment = Installment::factory()->overdue()->create();

        $this->getJson("/api/policies/{$installment->policy_id}")
            ->assertJsonPath('data.installments.0.status', ['value' => 'overdue', 'label' => 'Atrasado'])
            ->assertJsonPath('data.installments.0.is_overdue', true);

        $this->assertSame(InstallmentStatus::Pending, $installment->fresh()->status);
        $this->assertSame(PolicyStatus::Active, $installment->policy->status);
    }
}
