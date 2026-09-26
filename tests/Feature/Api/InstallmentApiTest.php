<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Installment;
use App\Models\Policy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallmentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsRole(UserRole::Assistant);
    }

    public function test_registers_a_payment(): void
    {
        $installment = Installment::factory()->overdue()->create();

        $this->postJson("/api/installments/{$installment->id}/payment", ['paid_on' => today()->toDateString()])
            ->assertOk()
            ->assertJsonPath('data.status', ['value' => 'paid', 'label' => 'Pago'])
            ->assertJsonPath('data.paid_on', today()->toDateString())
            ->assertJsonPath('data.is_overdue', false);
    }

    public function test_payment_rules(): void
    {
        $installment = Installment::factory()->create();

        $this->postJson("/api/installments/{$installment->id}/payment", ['paid_on' => today()->addDay()->toDateString()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('paid_on');

        $this->postJson("/api/installments/{$installment->id}/payment", ['paid_on' => today()->toDateString()])->assertOk();

        $this->postJson("/api/installments/{$installment->id}/payment", ['paid_on' => today()->toDateString()])
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'Somente parcelas pendentes podem ser pagas. Esta parcela está Pago.');

        $cancelled = Installment::factory()->cancelled()->create();
        $this->postJson("/api/installments/{$cancelled->id}/payment", ['paid_on' => today()->toDateString()])->assertUnprocessable();
    }

    public function test_filters_by_effective_status(): void
    {
        $overdue = Installment::factory()->overdue()->create();
        $upcoming = Installment::factory()->create(['due_date' => today()->addDays(5)]);
        Installment::factory()->paid()->create();

        $this->getJson('/api/installments?status=overdue')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $overdue->id)
            ->assertJsonPath('data.0.policy.client.id', $overdue->policy->client_id);

        $this->getJson('/api/installments?status=pending')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $upcoming->id);
        $this->getJson('/api/installments?status=paid')->assertJsonCount(1, 'data');
    }

    public function test_filters_by_policy_and_due_date_range(): void
    {
        $policy = Policy::factory()->active()->withInstallments()->create(['installments_count' => 3, 'starts_on' => today()->subMonth()]);
        Installment::factory()->create();

        $this->getJson("/api/installments?policy_id={$policy->id}")->assertJsonCount(3, 'data');
        $this->getJson("/api/installments?client_id={$policy->client_id}")->assertJsonCount(3, 'data');
        $this->getJson('/api/installments?policy_id='.$policy->id.'&due_from='.today()->toDateString())->assertJsonCount(2, 'data');
    }
}
