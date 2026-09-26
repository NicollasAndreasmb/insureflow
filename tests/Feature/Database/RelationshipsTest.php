<?php

namespace Tests\Feature\Database;

use App\Enums\ActivityAction;
use App\Enums\ClaimStatus;
use App\Models\ActivityLog;
use App\Models\Claim;
use App\Models\ClaimStatusHistory;
use App\Models\Client;
use App\Models\Coverage;
use App\Models\Policy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_policy_belongs_to_its_parties(): void
    {
        $policy = Policy::factory()->create();

        $this->assertTrue($policy->client->policies->contains($policy));
        $this->assertTrue($policy->insurer->policies->contains($policy));
        $this->assertTrue($policy->product->policies->contains($policy));
        $this->assertTrue($policy->broker->policies->contains($policy));
    }

    public function test_policy_aggregates_coverages_installments_and_claims(): void
    {
        $policy = Policy::factory()->withInstallments()->create(['premium' => '1000.00', 'installments_count' => 3]);
        Coverage::factory()->count(2)->create(['policy_id' => $policy->id]);
        Claim::factory()->create(['policy_id' => $policy->id]);

        $this->assertCount(2, $policy->coverages);
        $this->assertCount(1, $policy->claims);
        $this->assertSame([1, 2, 3], $policy->installments->pluck('number')->all());
        $this->assertSame(['333.34', '333.33', '333.33'], $policy->installments->pluck('amount')->all());
    }

    public function test_claim_reaches_its_client_through_the_policy(): void
    {
        $claim = Claim::factory()->create();

        $this->assertTrue($claim->client->is($claim->policy->client));
    }

    public function test_client_reaches_claims_and_installments_through_policies(): void
    {
        $client = Client::factory()->create();
        $policies = Policy::factory()->count(2)->withInstallments()->create([
            'client_id' => $client->id,
            'installments_count' => 2,
        ]);
        Claim::factory()->create(['policy_id' => $policies[0]->id]);
        Claim::factory()->create();

        $this->assertCount(1, $client->claims);
        $this->assertCount(4, $client->installments);
    }

    public function test_soft_deleted_policies_are_hidden_from_the_client(): void
    {
        $client = Client::factory()->create();
        $policy = Policy::factory()->create(['client_id' => $client->id]);

        $policy->delete();

        $this->assertCount(0, $client->policies);
        $this->assertSoftDeleted($policy);
    }

    public function test_claim_status_history_is_ordered_chronologically(): void
    {
        $claim = Claim::factory()->underReview()->create();
        ClaimStatusHistory::factory()->create([
            'claim_id' => $claim->id,
            'from_status' => ClaimStatus::Open,
            'to_status' => ClaimStatus::UnderReview,
            'created_at' => now(),
        ]);
        ClaimStatusHistory::factory()->create([
            'claim_id' => $claim->id,
            'to_status' => ClaimStatus::Open,
            'created_at' => now()->subDay(),
        ]);

        $this->assertSame(
            [ClaimStatus::Open, ClaimStatus::UnderReview],
            $claim->statusHistory->pluck('to_status')->all(),
        );
    }

    public function test_activity_logs_use_morph_aliases(): void
    {
        $policy = Policy::factory()->create();

        $log = $policy->activities()->create([
            'action' => ActivityAction::Updated,
            'changes' => ['old' => ['premium' => '100.00'], 'new' => ['premium' => '150.00']],
        ]);

        $this->assertDatabaseHas('activity_logs', ['id' => $log->id, 'subject_type' => 'policy']);

        $stored = ActivityLog::find($log->id);
        $this->assertTrue($stored->subject->is($policy));
        $this->assertSame('150.00', $stored->changes['new']['premium']);
    }
}
