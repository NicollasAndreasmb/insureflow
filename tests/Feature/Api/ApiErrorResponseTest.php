<?php

namespace Tests\Feature\Api;

use App\Exceptions\BusinessRuleException;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ApiErrorResponseTest extends TestCase
{
    public function test_unknown_route_returns_generic_not_found_message(): void
    {
        $this->getJson('/api/does-not-exist')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Recurso não encontrado.']);
    }

    public function test_wrong_method_returns_method_not_allowed(): void
    {
        $this->deleteJson('/api/health')
            ->assertMethodNotAllowed()
            ->assertExactJson(['message' => 'Método não permitido para este recurso.']);
    }

    public function test_unexpected_errors_hide_internal_details_when_debug_is_off(): void
    {
        config(['app.debug' => false]);
        Route::get('/api/_test/crash', fn () => throw new RuntimeException('SQL secret details'));

        $response = $this->getJson('/api/_test/crash')
            ->assertInternalServerError()
            ->assertExactJson(['message' => 'Ocorreu um erro interno. Tente novamente mais tarde.']);

        $this->assertStringNotContainsString('secret', $response->getContent());
    }

    public function test_business_rule_violations_use_the_validation_error_format(): void
    {
        Route::get('/api/_test/rule', fn () => throw new BusinessRuleException('Regra violada.', 'status'));

        $this->getJson('/api/_test/rule')
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Regra violada.',
                'errors' => ['status' => ['Regra violada.']],
            ]);
    }
}
