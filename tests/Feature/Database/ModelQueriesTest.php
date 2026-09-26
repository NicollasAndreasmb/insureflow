<?php

namespace Tests\Feature\Database;

use App\Enums\InstallmentStatus;
use App\Models\Installment;
use App\Models\Policy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelQueriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_installment_past_its_due_date_is_overdue(): void
    {
        $overdue = Installment::factory()->overdue()->create();
        $dueToday = Installment::factory()->create(['due_date' => today()]);
        $paidLate = Installment::factory()->create(['due_date' => today()->subDays(10)]);
        $paidLate->update(['status' => InstallmentStatus::Paid, 'paid_on' => today()]);

        $this->assertSame(InstallmentStatus::Overdue, $overdue->effectiveStatus());
        $this->assertSame(InstallmentStatus::Pending, $dueToday->effectiveStatus());
        $this->assertSame(InstallmentStatus::Paid, $paidLate->effectiveStatus());

        $this->assertSame([$overdue->id], Installment::overdue()->pluck('id')->all());
    }

    public function test_expiring_within_returns_only_active_policies_in_the_window(): void
    {
        $expiringSoon = Policy::factory()->expiringInDays(10)->create();
        Policy::factory()->expiringInDays(45)->create();
        Policy::factory()->suspended()->create(['starts_on' => today()->subYear()->addDays(5), 'ends_on' => today()->addDays(5)]);
        Policy::factory()->expired()->create();

        $this->assertSame([$expiringSoon->id], Policy::expiringWithin(30)->pluck('id')->all());
    }
}
