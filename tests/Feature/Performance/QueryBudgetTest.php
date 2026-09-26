<?php

namespace Tests\Feature\Performance;

use App\Enums\UserRole;
use App\Models\Claim;
use App\Models\Client;
use App\Models\Installment;
use App\Models\Policy;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Protege contra N+1: cada listagem deve executar o mesmo número de queries
 * com 5 ou com 15 registros na página. Se alguém remover um eager load, a
 * contagem cresce com os registros e o teste falha.
 */
class QueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    public static function listings(): array
    {
        return [
            'clients' => ['/api/clients', fn (int $n) => Policy::factory()->count($n)->create()],
            'policies' => ['/api/policies', fn (int $n) => Policy::factory()->count($n)->create()],
            'installments' => ['/api/installments', fn (int $n) => Installment::factory()->count($n)->create()],
            'claims' => ['/api/claims', fn (int $n) => Claim::factory()->count($n)->create()],
            'activity logs' => ['/api/activity-logs', fn (int $n) => Client::factory()->count($n)->create()],
        ];
    }

    #[DataProvider('listings')]
    public function test_listing_query_count_does_not_grow_with_rows(string $uri, Closure $seed): void
    {
        $this->actingAsRole(UserRole::Admin);

        $seed(5);
        $withFew = $this->countQueries($uri);

        $seed(10);
        $withMany = $this->countQueries($uri);

        $this->assertSame($withFew, $withMany, "{$uri}: {$withFew} queries with few rows, {$withMany} with many");
        $this->assertLessThanOrEqual(8, $withMany);
    }

    public function test_policy_detail_loads_its_aggregate_with_a_fixed_number_of_queries(): void
    {
        $this->actingAsRole(UserRole::Admin);
        $policy = Policy::factory()->active()->withInstallments()->create(['installments_count' => 12]);
        Claim::factory()->count(3)->create(['policy_id' => $policy->id]);

        $this->assertLessThanOrEqual(12, $this->countQueries("/api/policies/{$policy->id}"));
    }

    private function countQueries(string $uri): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson($uri)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
