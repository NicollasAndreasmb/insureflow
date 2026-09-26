<?php

namespace Tests\Unit\Enums;

use App\Enums\ClaimStatus;
use PHPUnit\Framework\TestCase;

class ClaimStatusTest extends TestCase
{
    public function test_claim_can_only_be_closed_after_a_decision(): void
    {
        $this->assertTrue(ClaimStatus::Approved->canTransitionTo(ClaimStatus::Closed));
        $this->assertTrue(ClaimStatus::Denied->canTransitionTo(ClaimStatus::Closed));

        $this->assertFalse(ClaimStatus::Open->canTransitionTo(ClaimStatus::Closed));
        $this->assertFalse(ClaimStatus::UnderReview->canTransitionTo(ClaimStatus::Closed));
        $this->assertFalse(ClaimStatus::PendingDocuments->canTransitionTo(ClaimStatus::Closed));
    }

    public function test_approval_requires_review(): void
    {
        $this->assertFalse(ClaimStatus::Open->canTransitionTo(ClaimStatus::Approved));
        $this->assertTrue(ClaimStatus::UnderReview->canTransitionTo(ClaimStatus::Approved));
    }

    public function test_pending_documents_returns_to_review(): void
    {
        $this->assertTrue(ClaimStatus::UnderReview->canTransitionTo(ClaimStatus::PendingDocuments));
        $this->assertTrue(ClaimStatus::PendingDocuments->canTransitionTo(ClaimStatus::UnderReview));
    }

    public function test_closed_is_the_only_final_state(): void
    {
        $finalStates = array_filter(ClaimStatus::cases(), fn (ClaimStatus $status) => $status->isFinal());

        $this->assertSame([ClaimStatus::Closed], array_values($finalStates));
    }
}
