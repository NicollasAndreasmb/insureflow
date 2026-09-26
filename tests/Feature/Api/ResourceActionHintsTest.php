<?php

namespace Tests\Feature\Api;

use App\Enums\ClaimStatus;
use App\Enums\UserRole;
use App\Models\Claim;
use App\Models\Installment;
use App\Models\Policy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Os resources expõem o que é possível fazer com cada registro, calculado pelas
 * mesmas regras que o backend aplica, para que o frontend não as replique.
 */
class ResourceActionHintsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsRole(UserRole::Admin);
    }

    public function test_policy_exposes_allowed_transitions_and_state_flags(): void
    {
        $quote = Policy::factory()->quote()->create();
        $cancelled = Policy::factory()->cancelled()->create();

        $this->getJson("/api/policies/{$quote->id}")
            ->assertJsonPath('data.allowed_transitions', [
                ['value' => 'pending', 'label' => 'Pendente'],
                ['value' => 'cancelled', 'label' => 'Cancelada'],
            ])
            ->assertJsonPath('data.is_editable', true)
            ->assertJsonPath('data.is_deletable', true);

        $this->getJson("/api/policies/{$cancelled->id}")
            ->assertJsonPath('data.allowed_transitions', [])
            ->assertJsonPath('data.is_editable', false)
            ->assertJsonPath('data.is_deletable', false);
    }

    public function test_claim_exposes_allowed_transitions(): void
    {
        $claim = Claim::factory()->underReview()->create();
        $closed = Claim::factory()->create(['status' => ClaimStatus::Closed]);

        $this->getJson("/api/claims/{$claim->id}")
            ->assertJsonPath('data.allowed_transitions.*.value', ['pending_documents', 'approved', 'denied'])
            ->assertJsonPath('data.is_editable', true);

        $this->getJson("/api/claims/{$closed->id}")
            ->assertJsonPath('data.allowed_transitions', [])
            ->assertJsonPath('data.is_editable', false);
    }

    public function test_installment_exposes_whether_it_can_be_paid(): void
    {
        $overdue = Installment::factory()->overdue()->create();
        $paid = Installment::factory()->paid()->create();

        $this->getJson("/api/installments/{$overdue->id}")->assertJsonPath('data.is_payable', true);
        $this->getJson("/api/installments/{$paid->id}")->assertJsonPath('data.is_payable', false);
    }

    public function test_options_expose_audit_labels(): void
    {
        $this->getJson('/api/options')
            ->assertJsonPath('data.activity_actions.0', ['value' => 'created', 'label' => 'Criação'])
            ->assertJsonFragment(['value' => 'policy', 'label' => 'Apólice'])
            ->assertJsonPath('data.policy_initial_statuses.*.value', ['quote', 'pending', 'active'])
            ->assertJsonPath('data.field_labels.premium', 'prêmio')
            ->assertJsonMissingPath('data.field_labels.coverages\.\*\.name');
    }
}
