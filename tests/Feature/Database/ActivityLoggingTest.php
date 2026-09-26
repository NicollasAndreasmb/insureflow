<?php

namespace Tests\Feature\Database;

use App\Enums\ActivityAction;
use App\Enums\PolicyStatus;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Policy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_is_logged_with_the_authenticated_user(): void
    {
        $user = $this->actingAsRole(UserRole::Broker);

        $client = Client::factory()->create(['name' => 'Maria Souza']);

        $log = $client->activities()->sole();
        $this->assertSame(ActivityAction::Created, $log->action);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('Maria Souza', $log->changes['new']['name']);
        $this->assertArrayNotHasKey('created_at', $log->changes['new']);
    }

    public function test_updates_store_only_changed_fields(): void
    {
        $client = Client::factory()->create(['name' => 'Maria Souza', 'city' => 'Campinas']);

        $client->update(['name' => 'Maria S. Lima']);

        $log = $client->activities()->where('action', ActivityAction::Updated)->sole();
        $this->assertEquals(['old' => ['name' => 'Maria Souza'], 'new' => ['name' => 'Maria S. Lima']], $log->changes);
    }

    public function test_saving_without_changes_is_not_logged(): void
    {
        $client = Client::factory()->create();

        $client->save();
        $client->touch();

        $this->assertSame(1, $client->activities()->count());
    }

    public function test_status_changes_are_logged_as_status_changed(): void
    {
        $policy = Policy::factory()->active()->create();

        $policy->update(['status' => PolicyStatus::Suspended]);

        $log = $policy->activities()->where('action', ActivityAction::StatusChanged)->sole();
        $this->assertEquals(['old' => ['status' => 'active'], 'new' => ['status' => 'suspended']], $log->changes);
    }

    public function test_deletion_and_restoration_are_logged(): void
    {
        $client = Client::factory()->create();

        $client->delete();
        $client->restore();

        $this->assertSame(
            [ActivityAction::Restored, ActivityAction::Deleted, ActivityAction::Created],
            $client->activities()->pluck('action')->all(),
        );
    }

    public function test_sensitive_user_fields_are_never_logged(): void
    {
        $user = User::factory()->create();

        $user->update(['password' => 'new-secret-password', 'name' => 'Novo Nome']);

        $payloads = ActivityLog::where('subject_type', 'user')->pluck('changes')->toJson();
        $this->assertStringNotContainsString('password', $payloads);
        $this->assertStringNotContainsString('remember_token', $payloads);
        $this->assertStringContainsString('Novo Nome', $payloads);
    }

    public function test_operations_without_an_authenticated_user_are_attributed_to_the_system(): void
    {
        $client = Client::factory()->create();

        $this->assertNull($client->activities()->sole()->user_id);
    }
}
