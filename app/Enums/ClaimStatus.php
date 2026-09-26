<?php

namespace App\Enums;

/**
 * Fluxo de atendimento de um sinistro.
 *
 * Cada transição efetivada é gravada em claim_status_histories. Um sinistro só
 * é encerrado depois de uma decisão (aprovado ou negado), para que o histórico
 * sempre registre o desfecho.
 *
 *   open -> under_review <-> pending_documents
 *                |                  |
 *                +-> approved / denied <-+
 *                        |
 *                        +-> closed
 */
enum ClaimStatus: string
{
    case Open = 'open';
    case UnderReview = 'under_review';
    case PendingDocuments = 'pending_documents';
    case Approved = 'approved';
    case Denied = 'denied';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Aberto',
            self::UnderReview => 'Em análise',
            self::PendingDocuments => 'Documentação pendente',
            self::Approved => 'Aprovado',
            self::Denied => 'Negado',
            self::Closed => 'Encerrado',
        };
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::UnderReview, self::PendingDocuments, self::Denied],
            self::UnderReview => [self::PendingDocuments, self::Approved, self::Denied],
            self::PendingDocuments => [self::UnderReview, self::Denied],
            self::Approved, self::Denied => [self::Closed],
            self::Closed => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }
}
