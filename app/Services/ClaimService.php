<?php

namespace App\Services;

use App\Enums\ClaimStatus;
use App\Enums\PolicyStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Claim;
use App\Models\Policy;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Regras de abertura e acompanhamento de sinistros.
 *
 * - Elegibilidade: a apólice precisa estar ativa, ou expirada quando a
 *   ocorrência aconteceu dentro da vigência (o sinistro pode ser comunicado
 *   depois do fim do contrato). Em qualquer caso a data de ocorrência deve
 *   estar dentro da vigência; datas futuras são barradas na validação.
 * - Protocolo: sequencial por ano de comunicação (SIN-2026-000042). A geração
 *   usa um advisory lock transacional do PostgreSQL para que duas aberturas
 *   simultâneas não calculem o mesmo número.
 * - Status: transições definidas por ClaimStatus::canTransitionTo(); cada
 *   mudança grava uma linha imutável em claim_status_histories. Aprovação exige
 *   valor aprovado e negativa o remove, coerente com as constraints do banco.
 */
class ClaimService
{
    private const PROTOCOL_LOCK_KEY = 7_470_001;

    private const ELIGIBLE_POLICY_STATUSES = [PolicyStatus::Active, PolicyStatus::Expired];

    public function open(array $data, ?User $user): Claim
    {
        $policy = Policy::findOrFail($data['policy_id']);
        $occurredOn = CarbonImmutable::parse($data['occurred_on']);

        $this->ensurePolicyCovers($policy, $occurredOn);

        $data['reported_on'] ??= today()->toDateString();

        return DB::transaction(function () use ($data, $user) {
            DB::select('select pg_advisory_xact_lock(?)', [self::PROTOCOL_LOCK_KEY]);

            $claim = Claim::create([
                ...$data,
                'protocol' => $this->nextProtocol(CarbonImmutable::parse($data['reported_on'])->year),
                'status' => ClaimStatus::Open,
            ]);

            $claim->statusHistory()->create([
                'from_status' => null,
                'to_status' => ClaimStatus::Open,
                'user_id' => $user?->id,
                'notes' => 'Sinistro aberto.',
            ]);

            return $claim;
        });
    }

    public function update(Claim $claim, array $data): Claim
    {
        if (! $claim->isEditable()) {
            throw new BusinessRuleException('Sinistros encerrados não podem ser alterados.', 'status');
        }

        $claim->update($data);

        return $claim;
    }

    public function changeStatus(Claim $claim, ClaimStatus $status, ?User $user, ?string $approvedAmount = null, ?string $notes = null): Claim
    {
        $from = $claim->status;

        if (! $from->canTransitionTo($status)) {
            throw new BusinessRuleException(
                "Não é possível alterar o status de {$from->label()} para {$status->label()}.",
                'status',
            );
        }

        if ($status === ClaimStatus::Approved && $approvedAmount === null) {
            throw new BusinessRuleException('Informe o valor aprovado para aprovar o sinistro.', 'approved_amount');
        }

        $attributes = ['status' => $status];

        if ($status === ClaimStatus::Approved) {
            $attributes['approved_amount'] = $approvedAmount;
        } elseif ($status === ClaimStatus::Denied) {
            $attributes['approved_amount'] = null;
        }

        return DB::transaction(function () use ($claim, $attributes, $from, $status, $user, $notes) {
            $claim->update($attributes);

            $claim->statusHistory()->create([
                'from_status' => $from,
                'to_status' => $status,
                'user_id' => $user?->id,
                'notes' => $notes,
            ]);

            return $claim;
        });
    }

    private function ensurePolicyCovers(Policy $policy, CarbonImmutable $occurredOn): void
    {
        if (! in_array($policy->status, self::ELIGIBLE_POLICY_STATUSES, true)) {
            throw new BusinessRuleException(
                "Não é possível abrir sinistro para uma apólice com status {$policy->status->label()}.",
                'policy_id',
            );
        }

        if ($occurredOn->lt($policy->starts_on) || $occurredOn->gt($policy->ends_on)) {
            throw new BusinessRuleException(sprintf(
                'A data de ocorrência deve estar dentro da vigência da apólice (%s a %s).',
                $policy->starts_on->format('d/m/Y'),
                $policy->ends_on->format('d/m/Y'),
            ), 'occurred_on');
        }
    }

    private function nextProtocol(int $year): string
    {
        $prefix = "SIN-{$year}-";

        $last = Claim::withTrashed()->where('protocol', 'like', "{$prefix}%")->max('protocol');
        $sequence = $last === null ? 1 : ((int) substr($last, strlen($prefix))) + 1;

        return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
