<?php

namespace Tests\Feature\Database;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TimestampTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_timestamps_round_trip_as_the_same_instant(): void
    {
        config(['app.timezone' => 'America/Sao_Paulo']);

        $client = Client::factory()->create();

        $storedEpoch = (int) DB::scalar('select extract(epoch from created_at) from clients where id = ?', [$client->id]);

        $this->assertEqualsWithDelta(now()->getTimestamp(), $storedEpoch, 5);
        $this->assertEqualsWithDelta(now()->getTimestamp(), $client->fresh()->created_at->getTimestamp(), 5);
    }
}
