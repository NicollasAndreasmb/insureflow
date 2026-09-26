<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Varre todas as rotas registradas em /api: qualquer rota nova que esqueça o
 * middleware de autenticação faz este teste falhar, em vez de depender de
 * alguém lembrar de testá-la.
 */
class RouteProtectionTest extends TestCase
{
    use RefreshDatabase;

    private const PUBLIC_ROUTES = ['api/health', 'api/login'];

    public function test_every_api_route_except_public_ones_requires_authentication(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route) => str_starts_with($route->uri(), 'api/'))
            ->reject(fn (RoutingRoute $route) => in_array($route->uri(), self::PUBLIC_ROUTES, true));

        $this->assertGreaterThanOrEqual(45, $routes->count());

        foreach ($routes as $route) {
            $method = collect($route->methods())->reject(fn (string $m) => $m === 'HEAD')->first();
            $uri = '/'.preg_replace('/\{[^}]+\}/', '1', $route->uri());

            $this->json($method, $uri)->assertUnauthorized();
        }
    }

    public function test_non_numeric_or_out_of_range_identifiers_return_not_found(): void
    {
        $this->actingAsRole(UserRole::Admin);

        foreach (['/api/clients/abc', '/api/policies/99999999999999999999', '/api/claims/1.5', '/api/coverages/x', '/api/installments/-1'] as $uri) {
            $this->getJson($uri)
                ->assertNotFound()
                ->assertExactJson(['message' => 'Recurso não encontrado.']);
        }
    }
}
