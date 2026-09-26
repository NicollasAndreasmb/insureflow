<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Coverage;
use App\Models\Policy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoverageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_broker_manages_coverages_of_a_policy(): void
    {
        $this->actingAsRole(UserRole::Broker);
        $policy = Policy::factory()->active()->create();

        $id = $this->postJson("/api/policies/{$policy->id}/coverages", ['name' => 'Vendaval', 'coverage_limit' => '50000'])
            ->assertCreated()
            ->assertJsonPath('data.additional_premium', '0.00')
            ->json('data.id');

        $this->postJson("/api/policies/{$policy->id}/coverages", ['name' => 'Vendaval', 'coverage_limit' => '1'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->putJson("/api/coverages/{$id}", ['name' => 'Vendaval e granizo', 'coverage_limit' => '60000'])
            ->assertOk()
            ->assertJsonPath('data.coverage_limit', '60000.00');

        $this->getJson("/api/policies/{$policy->id}/coverages")->assertOk()->assertJsonCount(1, 'data');

        $this->deleteJson("/api/coverages/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('coverages', ['id' => $id]);
    }

    public function test_coverages_of_final_policies_are_read_only(): void
    {
        $this->actingAsRole(UserRole::Broker);
        $coverage = Coverage::factory()->create(['policy_id' => Policy::factory()->cancelled()]);

        $this->putJson("/api/coverages/{$coverage->id}", ['name' => 'Outra', 'coverage_limit' => '1'])->assertUnprocessable();
        $this->deleteJson("/api/coverages/{$coverage->id}")->assertUnprocessable();
        $this->postJson("/api/policies/{$coverage->policy_id}/coverages", ['name' => 'Nova', 'coverage_limit' => '1'])->assertUnprocessable();
    }

    public function test_assistant_cannot_change_coverages(): void
    {
        $this->actingAsRole(UserRole::Assistant);
        $coverage = Coverage::factory()->create();

        $this->getJson("/api/coverages/{$coverage->id}")->assertOk();
        $this->postJson("/api/policies/{$coverage->policy_id}/coverages", ['name' => 'Nova', 'coverage_limit' => '1'])->assertForbidden();
        $this->deleteJson("/api/coverages/{$coverage->id}")->assertForbidden();
    }
}
