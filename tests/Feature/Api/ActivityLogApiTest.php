<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_operations_are_audited_and_listed_by_subject(): void
    {
        $broker = $this->actingAsRole(UserRole::Broker);

        $clientId = $this->postJson('/api/clients', [
            'person_type' => 'individual', 'name' => 'Maria Souza', 'document' => '52998224725',
        ])->json('data.id');
        $this->putJson("/api/clients/{$clientId}", [
            'person_type' => 'individual', 'name' => 'Maria S. Lima', 'document' => '52998224725',
        ])->assertOk();
        $this->deleteJson("/api/clients/{$clientId}")->assertNoContent();

        $this->getJson("/api/activity-logs?subject_type=client&subject_id={$clientId}")
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.action.value', 'deleted')
            ->assertJsonPath('data.1.action.value', 'updated')
            ->assertJsonPath('data.1.changes.new.name', 'Maria S. Lima')
            ->assertJsonPath('data.1.user', ['id' => $broker->id, 'name' => $broker->name])
            ->assertJsonPath('data.2.action.value', 'created');
    }

    public function test_user_audit_trail_is_visible_only_to_admins(): void
    {
        $user = User::factory()->create();
        Client::factory()->create();

        $this->actingAsRole(UserRole::Broker);
        $this->getJson("/api/activity-logs?subject_type=user&subject_id={$user->id}")->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/activity-logs')->assertOk()->assertJsonMissing(['subject_type' => 'user']);

        $this->actingAsRole(UserRole::Admin);
        $this->getJson("/api/activity-logs?subject_type=user&subject_id={$user->id}")->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_rejects_unknown_subject_types(): void
    {
        $this->actingAsRole(UserRole::Assistant);

        $this->getJson('/api/activity-logs?subject_type=App\Models\Client&subject_id=1')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('subject_type');
    }
}
