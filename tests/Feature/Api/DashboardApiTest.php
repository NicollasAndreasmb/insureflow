<?php

namespace Tests\Feature\Api;

use App\Enums\ClaimStatus;
use App\Enums\UserRole;
use App\Models\Claim;
use App\Models\Client;
use App\Models\Installment;
use App\Models\InsuranceProduct;
use App\Models\Policy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_reflect_the_database(): void
    {
        $this->actingAsRole(UserRole::Assistant);

        $auto = InsuranceProduct::factory()->create(['name' => 'Seguro Auto']);
        $life = InsuranceProduct::factory()->create(['name' => 'Seguro de Vida']);
        InsuranceProduct::factory()->create(['name' => 'Seguro Pet']);
        Client::factory()->inactive()->create();

        $expiring = Policy::factory()->expiringInDays(5)->create([
            'insurance_product_id' => $auto->id, 'premium' => '1000.00', 'commission_rate' => '10.00',
        ]);
        Policy::factory()->active()->create([
            'insurance_product_id' => $auto->id, 'premium' => '2000.50', 'commission_rate' => '20.00',
            'starts_on' => today()->subMonth(), 'ends_on' => today()->subMonth()->addYear(),
        ]);
        Policy::factory()->quote()->create(['insurance_product_id' => $life->id, 'premium' => '999.99']);
        Policy::factory()->active()->create(['insurance_product_id' => $life->id])->delete();

        Installment::factory()->overdue()->create(['policy_id' => $expiring->id, 'number' => 1, 'amount' => '100.00']);
        Installment::factory()->create(['policy_id' => $expiring->id, 'number' => 2, 'amount' => '200.00', 'due_date' => today()->addDays(10)]);
        Installment::factory()->paid()->create(['policy_id' => $expiring->id, 'number' => 3, 'amount' => '300.00', 'due_date' => today()->subDays(40)]);

        Claim::factory()->create(['policy_id' => $expiring->id]);
        Claim::factory()->underReview()->create(['policy_id' => $expiring->id]);
        Claim::factory()->create(['policy_id' => $expiring->id, 'status' => ClaimStatus::Closed]);

        $response = $this->getJson('/api/dashboard')->assertOk();

        $response
            ->assertJsonPath('data.clients.total', 5)
            ->assertJsonPath('data.clients.active', 4)
            ->assertJsonPath('data.policies.total', 3)
            ->assertJsonPath('data.policies.active', 2)
            ->assertJsonPath('data.policies.expiring_soon', 1)
            ->assertJsonPath('data.premiums.active_total', '3000.50')
            ->assertJsonPath('data.premiums.active_commission_total', '500.10')
            ->assertJsonPath('data.installments.overdue', ['label' => 'Atrasado', 'count' => 1, 'amount' => '100.00'])
            ->assertJsonPath('data.installments.pending.amount', '200.00')
            ->assertJsonPath('data.installments.paid.count', 1)
            ->assertJsonPath('data.claims.open', 2)
            ->assertJsonPath('data.claims.total', 3)
            ->assertJsonPath('data.upcoming_expirations.0.id', $expiring->id)
            ->assertJsonPath('data.upcoming_expirations.0.days_left', 5);

        $byProduct = collect($response->json('data.policies_by_product'))->keyBy('name');
        $this->assertSame(2, $byProduct['Seguro Auto']['policies_count']);
        $this->assertSame('3000.50', $byProduct['Seguro Auto']['active_premium']);
        $this->assertSame(1, $byProduct['Seguro de Vida']['policies_count']);
        $this->assertSame(0, $byProduct['Seguro Pet']['policies_count']);

        $statuses = collect($response->json('data.policies.by_status'))->pluck('count', 'value');
        $this->assertSame(['quote' => 1, 'pending' => 0, 'active' => 2, 'suspended' => 0, 'cancelled' => 0, 'expired' => 0], $statuses->all());
    }

    public function test_dashboard_runs_a_fixed_number_of_queries(): void
    {
        $this->actingAsRole(UserRole::Broker);
        Policy::factory()->count(15)->expiringInDays(3)->withInstallments()->create();

        DB::enableQueryLog();
        $this->getJson('/api/dashboard')->assertOk()->assertJsonCount(10, 'data.upcoming_expirations');
        $queries = count(DB::getQueryLog());

        $this->assertLessThanOrEqual(15, $queries, "Dashboard executed {$queries} queries");
    }

    public function test_expiring_window_is_configurable_and_validated(): void
    {
        $this->actingAsRole(UserRole::Broker);
        Policy::factory()->expiringInDays(45)->create();

        $this->getJson('/api/dashboard')->assertJsonPath('data.policies.expiring_soon', 0);
        $this->getJson('/api/dashboard?expiring_within_days=60')->assertJsonPath('data.policies.expiring_soon', 1);
        $this->getJson('/api/dashboard?expiring_within_days=500')->assertJsonValidationErrors('expiring_within_days');
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }
}
