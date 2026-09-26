<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OptionsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_exposes_enum_options_with_labels(): void
    {
        $this->actingAsRole(UserRole::Assistant);

        $this->getJson('/api/options')
            ->assertOk()
            ->assertJsonPath('data.policy_statuses.0', ['value' => 'quote', 'label' => 'Cotação'])
            ->assertJsonPath('data.payment_methods.0', ['value' => 'bank_slip', 'label' => 'Boleto'])
            ->assertJsonCount(27, 'data.states')
            ->assertJsonStructure(['data' => ['person_types', 'installment_statuses', 'claim_statuses', 'user_roles']]);
    }
}
