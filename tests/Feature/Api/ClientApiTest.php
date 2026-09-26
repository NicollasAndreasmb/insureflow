<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Policy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientApiTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return [
            'person_type' => 'individual',
            'name' => 'Maria Souza',
            'document' => '529.982.247-25',
            'email' => 'maria@example.com',
            'mobile' => '(11) 98765-4321',
            'birth_date' => '1985-04-12',
            'zip_code' => '01310-100',
            'city' => 'São Paulo',
            'state' => 'sp',
            ...$overrides,
        ];
    }

    public function test_creates_a_client_with_normalized_fields(): void
    {
        $this->actingAsRole(UserRole::Assistant);

        $this->postJson('/api/clients', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.document', '52998224725')
            ->assertJsonPath('data.mobile', '11987654321')
            ->assertJsonPath('data.zip_code', '01310100')
            ->assertJsonPath('data.state', 'SP')
            ->assertJsonPath('data.person_type', ['value' => 'individual', 'label' => 'Pessoa física']);

        $this->assertDatabaseHas('clients', ['document' => '52998224725', 'name' => 'Maria Souza']);
    }

    public function test_validates_required_fields_and_formats(): void
    {
        $this->actingAsRole(UserRole::Broker);

        $this->postJson('/api/clients', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['person_type', 'name', 'document']);

        $this->postJson('/api/clients', $this->validPayload([
            'document' => '529.982.247-24',
            'email' => 'not-an-email',
            'birth_date' => now()->addDay()->toDateString(),
            'state' => 'XX',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['document', 'email', 'birth_date', 'state'])
            ->assertJsonPath('errors.document.0', 'O CPF informado é inválido.');
    }

    public function test_document_must_match_the_person_type(): void
    {
        $this->actingAsRole(UserRole::Broker);

        $this->postJson('/api/clients', $this->validPayload(['person_type' => 'company']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.document.0', 'O CNPJ informado é inválido.');

        $this->postJson('/api/clients', $this->validPayload(['person_type' => 'company', 'document' => '11.222.333/0001-81']))
            ->assertCreated();
    }

    public function test_document_must_be_unique_among_active_clients(): void
    {
        $this->actingAsRole(UserRole::Broker);
        $existing = Client::factory()->create(['document' => '52998224725']);

        $this->postJson('/api/clients', $this->validPayload())->assertUnprocessable()->assertJsonValidationErrors('document');

        $this->putJson("/api/clients/{$existing->id}", $this->validPayload(['name' => 'Maria Atualizada']))
            ->assertOk()
            ->assertJsonPath('data.name', 'Maria Atualizada');

        $existing->delete();
        $this->postJson('/api/clients', $this->validPayload())->assertCreated();
    }

    public function test_lists_clients_with_search_filters_and_pagination(): void
    {
        $this->actingAsRole(UserRole::Assistant);
        Client::factory()->create(['name' => 'Ana Beatriz Lima', 'state' => 'SP']);
        Client::factory()->create(['name' => 'Carlos Pereira', 'state' => 'RJ']);
        Client::factory()->company()->create(['name' => 'Lima Transportes Ltda', 'state' => 'SP']);

        $this->getJson('/api/clients?search=lima&sort=name')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Ana Beatriz Lima')
            ->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'total', 'per_page']]);

        $this->getJson('/api/clients?person_type=company')->assertJsonCount(1, 'data');
        $this->getJson('/api/clients?state=RJ')->assertJsonCount(1, 'data');
        $this->getJson('/api/clients?per_page=1')->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 3);
        $this->getJson('/api/clients?sort=password')->assertUnprocessable()->assertJsonValidationErrors('sort');
    }

    public function test_shows_client_details_with_policies(): void
    {
        $this->actingAsRole(UserRole::Assistant);
        $client = Client::factory()->create();
        Policy::factory()->count(2)->create(['client_id' => $client->id]);

        $this->getJson("/api/clients/{$client->id}")
            ->assertOk()
            ->assertJsonPath('data.policies_count', 2)
            ->assertJsonCount(2, 'data.policies')
            ->assertJsonStructure(['data' => ['policies' => [['number', 'status', 'insurer', 'product']]]]);
    }

    public function test_missing_client_returns_not_found(): void
    {
        $this->actingAsRole(UserRole::Assistant);

        $this->getJson('/api/clients/999999')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Recurso não encontrado.']);
    }

    public function test_deleting_is_restricted_by_role_and_open_policies(): void
    {
        $client = Client::factory()->create();

        $this->actingAsRole(UserRole::Assistant);
        $this->deleteJson("/api/clients/{$client->id}")->assertForbidden();

        $this->actingAsRole(UserRole::Broker);
        $policy = Policy::factory()->active()->create(['client_id' => $client->id]);
        $this->deleteJson("/api/clients/{$client->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'O cliente possui apólices em andamento. Inative-o em vez de excluí-lo.');

        $policy->update(['status' => 'cancelled']);
        $this->deleteJson("/api/clients/{$client->id}")->assertNoContent();
        $this->assertSoftDeleted($client);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/clients')->assertUnauthorized();
        $this->postJson('/api/clients', $this->validPayload())->assertUnauthorized();
    }
}
