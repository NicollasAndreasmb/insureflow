<?php

namespace Tests\Unit\Enums;

use App\Enums\PolicyStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PolicyStatusTest extends TestCase
{
    public static function validTransitions(): array
    {
        return [
            'quote to pending' => [PolicyStatus::Quote, PolicyStatus::Pending],
            'pending to active' => [PolicyStatus::Pending, PolicyStatus::Active],
            'active to suspended' => [PolicyStatus::Active, PolicyStatus::Suspended],
            'suspended back to active' => [PolicyStatus::Suspended, PolicyStatus::Active],
            'active to expired' => [PolicyStatus::Active, PolicyStatus::Expired],
            'quote to cancelled' => [PolicyStatus::Quote, PolicyStatus::Cancelled],
        ];
    }

    public static function invalidTransitions(): array
    {
        return [
            'quote straight to active' => [PolicyStatus::Quote, PolicyStatus::Active],
            'cancelled reactivated' => [PolicyStatus::Cancelled, PolicyStatus::Active],
            'expired reactivated' => [PolicyStatus::Expired, PolicyStatus::Active],
            'active back to quote' => [PolicyStatus::Active, PolicyStatus::Quote],
            'pending expiring before activation' => [PolicyStatus::Pending, PolicyStatus::Expired],
        ];
    }

    #[DataProvider('validTransitions')]
    public function test_allows_valid_transitions(PolicyStatus $from, PolicyStatus $to): void
    {
        $this->assertTrue($from->canTransitionTo($to));
    }

    #[DataProvider('invalidTransitions')]
    public function test_rejects_invalid_transitions(PolicyStatus $from, PolicyStatus $to): void
    {
        $this->assertFalse($from->canTransitionTo($to));
    }

    public function test_cancelled_and_expired_are_final_states(): void
    {
        $finalStates = array_filter(PolicyStatus::cases(), fn (PolicyStatus $status) => $status->isFinal());

        $this->assertEqualsCanonicalizing([PolicyStatus::Cancelled, PolicyStatus::Expired], array_values($finalStates));
    }
}
