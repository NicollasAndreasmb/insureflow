<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_manages_users(): void
    {
        $this->actingAsRole(UserRole::Admin);

        $id = $this->postJson('/api/users', [
            'name' => 'Paula Corretora',
            'email' => 'paula@insureflow.test',
            'password' => 'senhaSegura123',
            'role' => 'broker',
        ])
            ->assertCreated()
            ->assertJsonPath('data.role.value', 'broker')
            ->assertJsonMissingPath('data.password')
            ->json('data.id');

        $this->assertTrue(Hash::check('senhaSegura123', User::find($id)->password));

        $this->putJson("/api/users/{$id}", ['name' => 'Paula Lima', 'email' => 'paula@insureflow.test', 'role' => 'assistant', 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertTrue(Hash::check('senhaSegura123', User::find($id)->password), 'password kept when omitted');

        $this->getJson('/api/users?role=assistant')->assertOk()->assertJsonCount(1, 'data');

        $this->deleteJson("/api/users/{$id}")->assertNoContent();
        $this->assertSoftDeleted('users', ['id' => $id]);
    }

    public function test_user_validation(): void
    {
        $this->actingAsRole(UserRole::Admin);
        User::factory()->create(['email' => 'taken@insureflow.test']);

        $this->postJson('/api/users', ['name' => '', 'email' => 'taken@insureflow.test', 'password' => '123', 'role' => 'root'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password', 'role']);
    }

    public function test_emails_are_stored_lowercase_and_unique_regardless_of_case(): void
    {
        $this->actingAsRole(UserRole::Admin);
        User::factory()->create(['email' => 'paula@insureflow.test']);

        $this->postJson('/api/users', ['name' => 'Paula', 'email' => 'Paula@InsureFlow.test', 'password' => 'senhaSegura123', 'role' => 'broker'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->postJson('/api/users', ['name' => 'Rita', 'email' => 'Rita@InsureFlow.test', 'password' => 'senhaSegura123', 'role' => 'broker'])
            ->assertCreated()
            ->assertJsonPath('data.email', 'rita@insureflow.test');
    }

    public function test_admin_cannot_lock_themselves_out(): void
    {
        $admin = $this->actingAsRole(UserRole::Admin);
        $payload = ['name' => $admin->name, 'email' => $admin->email];

        $this->putJson("/api/users/{$admin->id}", [...$payload, 'role' => 'broker'])->assertJsonValidationErrors('role');
        $this->putJson("/api/users/{$admin->id}", [...$payload, 'role' => 'admin', 'is_active' => false])->assertJsonValidationErrors('is_active');
        $this->deleteJson("/api/users/{$admin->id}")->assertForbidden();
        $this->putJson("/api/users/{$admin->id}", [...$payload, 'name' => 'Novo Nome', 'role' => 'admin'])->assertOk();
    }

    public function test_non_admins_cannot_manage_users(): void
    {
        $other = User::factory()->create();

        foreach ([UserRole::Broker, UserRole::Assistant] as $role) {
            $this->actingAsRole($role);

            $this->getJson('/api/users')->assertForbidden();
            $this->getJson("/api/users/{$other->id}")->assertForbidden();
            $this->postJson('/api/users', [])->assertForbidden();
            $this->deleteJson("/api/users/{$other->id}")->assertForbidden();
        }
    }
}
