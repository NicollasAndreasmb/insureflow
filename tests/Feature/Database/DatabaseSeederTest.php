<?php

namespace Tests\Feature\Database;

use App\Enums\PolicyStatus;
use App\Enums\UserRole;
use App\Models\Claim;
use App\Models\Installment;
use App\Models\Policy;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Valida a coerência do conjunto de dados de demonstração, que alimenta o
 * dashboard e as telas durante o desenvolvimento.
 */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_produce_a_coherent_demo_dataset(): void
    {
        config(['seeding.default_password' => 'test-password']);

        $this->seed(DatabaseSeeder::class);

        foreach ([UserRole::Admin, UserRole::Broker, UserRole::Assistant] as $role) {
            $this->assertTrue(User::where('role', $role)->where('is_active', true)->exists(), "Missing active {$role->value}");
        }

        $installmentsNotMatchingPremium = DB::table('policies')
            ->join('installments', 'installments.policy_id', '=', 'policies.id')
            ->select('policies.id')
            ->groupBy('policies.id', 'policies.premium')
            ->havingRaw('sum(installments.amount) <> policies.premium')
            ->get()
            ->count();
        $this->assertSame(0, $installmentsNotMatchingPremium);

        $this->assertSame(0, Policy::where('status', PolicyStatus::Quote)->has('installments')->count());
        $this->assertSame(0, Policy::where('status', '<>', PolicyStatus::Quote)->doesntHave('installments')->count());

        $this->assertGreaterThan(0, Policy::expiringWithin(30)->count());
        $this->assertGreaterThan(0, Installment::overdue()->count());

        Claim::with('statusHistory')->get()->each(function (Claim $claim) {
            $this->assertSame($claim->status, $claim->statusHistory->last()->to_status, "Claim {$claim->protocol}");
            $this->assertNull($claim->statusHistory->first()->from_status);
        });
    }
}
