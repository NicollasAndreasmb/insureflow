<?php

namespace Tests\Unit;

use App\Support\InstallmentSchedule;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class InstallmentScheduleTest extends TestCase
{
    public function test_rounding_difference_goes_to_the_first_installment(): void
    {
        $schedule = InstallmentSchedule::build('1000.00', 3, CarbonImmutable::parse('2026-01-10'));

        $this->assertSame(['333.34', '333.33', '333.33'], array_column($schedule, 'amount'));
    }

    public function test_installments_always_add_up_to_the_premium(): void
    {
        foreach (['0.12', '99.99', '1234.57', '48999.01'] as $premium) {
            foreach (range(1, 12) as $count) {
                if ((float) $premium * 100 < $count) {
                    continue;
                }

                $schedule = InstallmentSchedule::build($premium, $count, CarbonImmutable::parse('2026-01-10'));
                $sum = array_reduce($schedule, fn (string $carry, array $item) => bcadd($carry, $item['amount'], 2), '0.00');

                $this->assertSame($premium, $sum, "{$premium} in {$count}x");
            }
        }
    }

    public function test_evenly_divisible_premium_has_equal_installments(): void
    {
        $schedule = InstallmentSchedule::build('1200.00', 12, CarbonImmutable::parse('2026-01-10'));

        $this->assertSame(['100.00'], array_values(array_unique(array_column($schedule, 'amount'))));
    }

    public function test_due_dates_are_monthly_without_overflowing_short_months(): void
    {
        $schedule = InstallmentSchedule::build('300.00', 3, CarbonImmutable::parse('2026-01-31'));

        $this->assertSame(
            ['2026-01-31', '2026-02-28', '2026-03-31'],
            array_map(fn (array $item) => $item['due_date']->toDateString(), $schedule),
        );
        $this->assertSame([1, 2, 3], array_column($schedule, 'number'));
    }

    public function test_rejects_invalid_installment_count(): void
    {
        $this->expectException(InvalidArgumentException::class);

        InstallmentSchedule::build('100.00', 0, CarbonImmutable::now());
    }

    public function test_rejects_premium_that_would_produce_zero_value_installments(): void
    {
        $this->expectException(InvalidArgumentException::class);

        InstallmentSchedule::build('0.05', 6, CarbonImmutable::now());
    }
}
