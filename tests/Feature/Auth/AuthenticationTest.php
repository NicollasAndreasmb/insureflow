<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_log_in_with_valid_credentials(): void
    {
        $user = User::factory()->create(['email' => 'ana@insureflow.test', 'role' => UserRole::Broker]);

        $this->postJson('/api/login', ['email' => 'ana@insureflow.test', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role', ['value' => 'broker', 'label' => 'Corretor'])
            ->assertJsonMissingPath('data.password');

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_session_cookie_is_http_only(): void
    {
        User::factory()->create(['email' => 'ana@insureflow.test']);

        $response = $this->postJson('/api/login', ['email' => 'ana@insureflow.test', 'password' => 'password']);

        $sessionCookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));

        $this->assertNotNull($sessionCookie);
        $this->assertTrue($sessionCookie->isHttpOnly());
    }

    public function test_wrong_password_and_unknown_email_get_the_same_message(): void
    {
        User::factory()->create(['email' => 'ana@insureflow.test']);

        $this->postJson('/api/login', ['email' => 'ana@insureflow.test', 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'E-mail ou senha inválidos.');

        $this->postJson('/api/login', ['email' => 'nobody@insureflow.test', 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'E-mail ou senha inválidos.');

        $this->assertGuest('web');
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['email', 'password']]);
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        User::factory()->inactive()->create(['email' => 'ana@insureflow.test']);

        $this->postJson('/api/login', ['email' => 'ana@insureflow.test', 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Esta conta está inativa. Procure um administrador.');

        $this->assertGuest('web');
    }

    public function test_soft_deleted_user_cannot_log_in(): void
    {
        $user = User::factory()->create(['email' => 'ana@insureflow.test']);
        $user->delete();

        $this->postJson('/api/login', ['email' => 'ana@insureflow.test', 'password' => 'password'])
            ->assertUnprocessable();
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'ana@insureflow.test']);

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/login', ['email' => 'ana@insureflow.test', 'password' => 'wrong'])->assertUnprocessable();
        }

        $this->postJson('/api/login', ['email' => 'ana@insureflow.test', 'password' => 'password'])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');
    }

    public function test_authenticated_user_endpoint_returns_the_current_user(): void
    {
        $user = $this->actingAsRole(UserRole::Assistant);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.role.value', 'assistant');
    }

    public function test_protected_routes_require_authentication(): void
    {
        $this->getJson('/api/user')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Não autenticado.']);

        $this->postJson('/api/logout')->assertUnauthorized();
    }

    public function test_user_can_log_out(): void
    {
        User::factory()->create(['email' => 'ana@insureflow.test']);
        $this->postJson('/api/login', ['email' => 'ana@insureflow.test', 'password' => 'password'])->assertOk();

        $this->postJson('/api/logout')->assertNoContent();

        $this->assertGuest('web');
    }

    public function test_session_of_a_user_deactivated_after_login_is_terminated(): void
    {
        $user = $this->actingAsRole(UserRole::Broker);
        $user->update(['is_active' => false]);

        $this->getJson('/api/user')
            ->assertForbidden()
            ->assertExactJson(['message' => 'Sua conta está inativa.']);

        $this->assertGuest('web');
    }
}
