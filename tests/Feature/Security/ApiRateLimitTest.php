<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_requests_are_limited_per_user(): void
    {
        $this->actingAsRole(UserRole::Assistant);

        foreach (range(1, 180) as $request) {
            $this->getJson('/api/user')->assertOk();
        }

        $this->getJson('/api/user')
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertExactJson(['message' => 'Muitas requisições. Aguarde um momento e tente novamente.']);

        $this->actingAs(User::factory()->create());
        $this->getJson('/api/user')->assertOk();
    }
}
