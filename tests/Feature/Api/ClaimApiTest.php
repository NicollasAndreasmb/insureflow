<?php

namespace Tests\Feature\Api;

use App\Enums\ClaimStatus;
use App\Enums\UserRole;
use App\Models\Claim;
use App\Models\Policy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClaimApiTest extends TestCase
{
    use RefreshDatabase;

    private User $assistant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assistant = $this->actingAsRole(UserRole::Assistant);
    }

    private function activePolicy(): Policy
    {
        return Policy::factory()->active()->create([
            'starts_on' => today()->subMonths(6),
            'ends_on' => today()->subMonths(6)->addYear(),
        ]);
    }

    private function payload(Policy $policy, array $overrides = []): array
    {
        return [
            'policy_id' => $policy->id,
            'occurred_on' => today()->subDays(3)->toDateString(),
            'description' => 'Colisão traseira em via urbana.',
            'estimated_amount' => '8500.00',
            ...$overrides,
        ];
    }

    public function test_opens_a_claim_with_protocol_and_initial_history(): void
    {
        $policy = $this->activePolicy();

        $this->postJson('/api/claims', $this->payload($policy))
            ->assertCreated()
            ->assertJsonPath('data.protocol', 'SIN-'.today()->year.'-000001')
            ->assertJsonPath('data.status.value', 'open')
            ->assertJsonPath('data.reported_on', today()->toDateString())
            ->assertJsonPath('data.policy.client.id', $policy->client_id)
            ->assertJsonPath('data.status_history.0.from_status', null)
            ->assertJsonPath('data.status_history.0.to_status.value', 'open')
            ->assertJsonPath('data.status_history.0.user.id', $this->assistant->id);
    }

    public function test_protocols_are_sequential_per_year_including_deleted_claims(): void
    {
        $policy = $this->activePolicy();
        Claim::factory()->create(['protocol' => 'SIN-'.today()->year.'-000041', 'policy_id' => $policy->id])->delete();

        $this->postJson('/api/claims', $this->payload($policy))
            ->assertCreated()
            ->assertJsonPath('data.protocol', 'SIN-'.today()->year.'-000042');
    }

    public static function ineligiblePolicies(): array
    {
        return [
            'quote' => ['quote'],
            'pending' => ['pending'],
            'suspended' => ['suspended'],
            'cancelled' => ['cancelled'],
        ];
    }

    #[DataProvider('ineligiblePolicies')]
    public function test_claims_require_an_eligible_policy(string $state): void
    {
        $policy = Policy::factory()->{$state}()->create([
            'starts_on' => today()->subMonths(2),
            'ends_on' => today()->subMonths(2)->addYear(),
        ]);

        $this->postJson('/api/claims', $this->payload($policy))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('policy_id');

        $this->assertDatabaseCount('claims', 0);
    }

    public function test_expired_policy_accepts_claims_that_occurred_during_its_term(): void
    {
        $policy = Policy::factory()->expired()->create([
            'starts_on' => today()->subYear()->subDays(10),
            'ends_on' => today()->subDays(10),
        ]);

        $this->postJson('/api/claims', $this->payload($policy, ['occurred_on' => today()->subDays(15)->toDateString()]))->assertCreated();
        $this->postJson('/api/claims', $this->payload($policy, ['occurred_on' => today()->subDays(5)->toDateString()]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('occurred_on');
    }

    public function test_occurrence_must_be_within_the_term_and_not_in_the_future(): void
    {
        $policy = $this->activePolicy();

        $this->postJson('/api/claims', $this->payload($policy, ['occurred_on' => $policy->starts_on->subDay()->toDateString()]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('occurred_on');

        $this->postJson('/api/claims', $this->payload($policy, ['occurred_on' => today()->addDay()->toDateString()]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('occurred_on');

        $this->postJson('/api/claims', $this->payload($policy, ['reported_on' => today()->subDays(5)->toDateString()]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reported_on');
    }

    public function test_status_workflow_records_history(): void
    {
        $claim = Claim::factory()->create();
        $claim->statusHistory()->create(['to_status' => ClaimStatus::Open]);

        $this->patchJson("/api/claims/{$claim->id}/status", ['status' => 'under_review'])->assertOk();
        $this->patchJson("/api/claims/{$claim->id}/status", ['status' => 'approved', 'approved_amount' => '7200.50', 'notes' => 'Laudo aprovado.'])
            ->assertOk()
            ->assertJsonPath('data.approved_amount', '7200.50');
        $this->patchJson("/api/claims/{$claim->id}/status", ['status' => 'closed'])
            ->assertOk()
            ->assertJsonPath('data.status.value', 'closed')
            ->assertJsonCount(4, 'data.status_history')
            ->assertJsonPath('data.status_history.2.notes', 'Laudo aprovado.')
            ->assertJsonPath('data.status_history.3.from_status.value', 'approved');
    }

    public function test_invalid_claim_transitions_are_rejected(): void
    {
        $claim = Claim::factory()->create();

        $this->patchJson("/api/claims/{$claim->id}/status", ['status' => 'closed'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'Não é possível alterar o status de Aberto para Encerrado.');

        $this->assertSame(0, $claim->statusHistory()->count());
    }

    public function test_approval_requires_an_amount_and_denial_forbids_it(): void
    {
        $claim = Claim::factory()->underReview()->create();

        $this->patchJson("/api/claims/{$claim->id}/status", ['status' => 'approved'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('approved_amount');

        $this->patchJson("/api/claims/{$claim->id}/status", ['status' => 'denied', 'approved_amount' => '100'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('approved_amount');

        $this->patchJson("/api/claims/{$claim->id}/status", ['status' => 'denied'])
            ->assertOk()
            ->assertJsonPath('data.approved_amount', null);
    }

    public function test_updates_descriptive_fields_until_closed(): void
    {
        $claim = Claim::factory()->create();

        $this->patchJson("/api/claims/{$claim->id}", ['estimated_amount' => '9100.00', 'notes' => 'Orçamento revisado.'])
            ->assertOk()
            ->assertJsonPath('data.estimated_amount', '9100.00');

        $this->patchJson("/api/claims/{$claim->id}", ['status' => 'closed', 'policy_id' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'policy_id']);

        $claim->update(['status' => ClaimStatus::Closed]);
        $this->patchJson("/api/claims/{$claim->id}", ['notes' => 'x'])->assertUnprocessable();
    }

    public function test_lists_claims_with_filters(): void
    {
        $policy = $this->activePolicy();
        Claim::factory()->create(['policy_id' => $policy->id]);
        Claim::factory()->approved()->create();
        Claim::factory()->create(['status' => ClaimStatus::Closed]);

        $this->getJson('/api/claims')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/claims?open=1')->assertJsonCount(2, 'data');
        $this->getJson('/api/claims?status=approved')->assertJsonCount(1, 'data');
        $this->getJson("/api/claims?client_id={$policy->client_id}")->assertJsonCount(1, 'data');
        $this->getJson('/api/claims?search='.$policy->number)->assertJsonCount(1, 'data');
    }

    public function test_only_admin_deletes_claims(): void
    {
        $claim = Claim::factory()->create();

        $this->deleteJson("/api/claims/{$claim->id}")->assertForbidden();

        $this->actingAsRole(UserRole::Admin);
        $this->deleteJson("/api/claims/{$claim->id}")->assertNoContent();
        $this->assertSoftDeleted($claim);
    }
}
