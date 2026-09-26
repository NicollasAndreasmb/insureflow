<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\InsuranceProduct;
use App\Models\Insurer;
use App\Models\Policy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Seguradoras e modalidades formam o catálogo: todos consultam, apenas o
 * administrador altera.
 */
class CatalogApiTest extends TestCase
{
    use RefreshDatabase;

    private function insurerPayload(array $overrides = []): array
    {
        return [
            'legal_name' => 'Aurora Seguros S.A.',
            'trade_name' => 'Aurora Seguros',
            'cnpj' => '11.222.333/0001-81',
            'phone' => '0800 123 4567',
            'website' => 'https://aurora.example',
            ...$overrides,
        ];
    }

    public function test_admin_manages_insurers(): void
    {
        $this->actingAsRole(UserRole::Admin);

        $id = $this->postJson('/api/insurers', $this->insurerPayload())
            ->assertCreated()
            ->assertJsonPath('data.cnpj', '11222333000181')
            ->json('data.id');

        $this->putJson("/api/insurers/{$id}", $this->insurerPayload(['trade_name' => 'Aurora']))
            ->assertOk()
            ->assertJsonPath('data.trade_name', 'Aurora');

        $this->getJson('/api/insurers?search=aurora')->assertOk()->assertJsonCount(1, 'data');

        $this->deleteJson("/api/insurers/{$id}")->assertNoContent();
        $this->assertSoftDeleted('insurers', ['id' => $id]);
    }

    public function test_insurer_validation(): void
    {
        $this->actingAsRole(UserRole::Admin);
        Insurer::factory()->create(['cnpj' => '11222333000181']);

        $this->postJson('/api/insurers', $this->insurerPayload(['website' => 'not a url']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cnpj', 'website']);

        $this->postJson('/api/insurers', $this->insurerPayload(['cnpj' => '11.222.333/0001-82']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.cnpj.0', 'O CNPJ informado é inválido.');
    }

    public function test_only_admin_changes_the_catalog(): void
    {
        $insurer = Insurer::factory()->create();
        $product = InsuranceProduct::factory()->create();

        foreach ([UserRole::Broker, UserRole::Assistant] as $role) {
            $this->actingAsRole($role);

            $this->getJson('/api/insurers')->assertOk();
            $this->getJson("/api/insurance-products/{$product->id}")->assertOk();
            $this->postJson('/api/insurers', $this->insurerPayload())->assertForbidden();
            $this->putJson("/api/insurers/{$insurer->id}", $this->insurerPayload())->assertForbidden();
            $this->deleteJson("/api/insurers/{$insurer->id}")->assertForbidden();
            $this->postJson('/api/insurance-products', ['name' => 'Seguro Pet'])->assertForbidden();
            $this->deleteJson("/api/insurance-products/{$product->id}")->assertForbidden();
        }
    }

    public function test_admin_creates_products_with_generated_slug(): void
    {
        $this->actingAsRole(UserRole::Admin);

        $this->postJson('/api/insurance-products', ['name' => 'Seguro Pet', 'description' => 'Cobertura veterinária.'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'seguro-pet')
            ->assertJsonPath('data.is_active', true);

        $this->postJson('/api/insurance-products', ['name' => 'Seguro Pet'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'slug']);
    }

    public function test_catalog_items_with_open_policies_cannot_be_deleted(): void
    {
        $this->actingAsRole(UserRole::Admin);
        $policy = Policy::factory()->active()->create();

        $this->deleteJson("/api/insurers/{$policy->insurer_id}")->assertUnprocessable();
        $this->deleteJson("/api/insurance-products/{$policy->insurance_product_id}")->assertUnprocessable();
    }
}
