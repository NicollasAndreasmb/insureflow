<?php

namespace Tests\Feature\Console;

use App\Enums\ActivityAction;
use App\Enums\PolicyStatus;
use App\Models\Policy;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpirePoliciesCommandTest extends TestCase
{
    use RefreshDatabase;

    private function endingOn(string $factoryState, $endsOn): Policy
    {
        return Policy::factory()->{$factoryState}()->create([
            'starts_on' => $endsOn->copy()->subYear(),
            'ends_on' => $endsOn,
        ]);
    }

    public function test_expires_active_and_suspended_policies_whose_term_ended(): void
    {
        $endedActive = $this->endingOn('active', today()->subDay());
        $endedSuspended = $this->endingOn('suspended', today()->subDays(3));
        $endsToday = $this->endingOn('active', today());
        $cancelled = $this->endingOn('cancelled', today()->subDays(10));

        $this->artisan('policies:expire')
            ->expectsOutput('2 apólice(s) expirada(s).')
            ->assertSuccessful();

        $this->assertSame(PolicyStatus::Expired, $endedActive->fresh()->status);
        $this->assertSame(PolicyStatus::Expired, $endedSuspended->fresh()->status);
        $this->assertSame(PolicyStatus::Active, $endsToday->fresh()->status);
        $this->assertSame(PolicyStatus::Cancelled, $cancelled->fresh()->status);
    }

    public function test_is_idempotent(): void
    {
        $policy = $this->endingOn('active', today()->subDay());

        $this->artisan('policies:expire')->expectsOutput('1 apólice(s) expirada(s).');
        $this->artisan('policies:expire')->expectsOutput('0 apólice(s) expirada(s).');

        $this->assertSame(1, $policy->activities()->where('action', ActivityAction::StatusChanged)->count());
    }

    public function test_expiration_is_audited_as_a_system_action(): void
    {
        $policy = $this->endingOn('active', today()->subDay());

        $this->artisan('policies:expire');

        $log = $policy->activities()->where('action', ActivityAction::StatusChanged)->sole();
        $this->assertNull($log->user_id);
        $this->assertEquals(['old' => ['status' => 'active'], 'new' => ['status' => 'expired']], $log->changes);
    }

    public function test_command_is_scheduled_daily(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn (Event $event) => str_contains($event->command, 'policies:expire'));

        $this->assertNotNull($event);
        $this->assertSame('5 0 * * *', $event->expression);
    }
}
