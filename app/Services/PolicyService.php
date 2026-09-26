<?php

namespace App\Services;

use App\Enums\InstallmentStatus;
use App\Enums\PolicyStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Policy;
use App\Support\InstallmentSchedule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Regras do ciclo de vida das apólices. Controllers apenas validam a entrada
 * e delegam para cá.
 *
 * - Parcelas: geradas por InstallmentSchedule quando a apólice deixa de ser
 *   cotação (cotações não têm cobrança). Alterar prêmio, quantidade de parcelas
 *   ou início da vigência regenera as parcelas, desde que nenhuma tenha sido paga.
 * - Status: as transições permitidas vêm de PolicyStatus::canTransitionTo();
 *   aqui ficam apenas as condições que dependem de datas e os efeitos
 *   colaterais (gerar parcelas ao emitir, cancelar pendentes ao cancelar).
 * - Estados finais (cancelada, expirada) só aceitam alteração de observações.
 * - Comissão: calculada pelo PostgreSQL (coluna gerada), nada a fazer aqui.
 *
 * A auditoria é automática (HasActivityLog), inclusive quando a expiração é
 * executada pelo scheduler, sem usuário autenticado.
 */
class PolicyService
{
    private const SCHEDULE_FIELDS = ['premium', 'installments_count', 'starts_on'];

    public function create(array $data): Policy
    {
        $coverages = Arr::pull($data, 'coverages', []);
        $status = PolicyStatus::from($data['status'] ?? PolicyStatus::Quote->value);

        $policy = new Policy($data);
        $this->ensureStatusFitsTerm($policy, $status);

        return DB::transaction(function () use ($policy, $coverages, $status) {
            $policy->save();
            $policy->coverages()->createMany($coverages);

            if ($status !== PolicyStatus::Quote) {
                $this->generateInstallments($policy);
            }

            return $policy;
        });
    }

    public function update(Policy $policy, array $data): Policy
    {
        if ($policy->status->isFinal() && array_keys(Arr::except($data, 'notes')) !== []) {
            throw new BusinessRuleException(
                "Apólices com status {$policy->status->label()} só permitem alterar as observações.",
                'status',
            );
        }

        $policy->fill($data);

        if (! $policy->status->isFinal()) {
            $this->ensureStatusFitsTerm($policy, $policy->status);
        }

        $mustRegenerateInstallments = $policy->isDirty(self::SCHEDULE_FIELDS) && $policy->installments()->exists();

        if ($mustRegenerateInstallments && $policy->installments()->where('status', InstallmentStatus::Paid)->exists()) {
            throw new BusinessRuleException(
                'Não é possível alterar prêmio, parcelas ou início da vigência de uma apólice com parcelas pagas.',
                'premium',
            );
        }

        return DB::transaction(function () use ($policy, $mustRegenerateInstallments) {
            $policy->save();

            if ($mustRegenerateInstallments) {
                $policy->installments()->delete();
                $this->generateInstallments($policy);
            }

            return $policy;
        });
    }

    public function changeStatus(Policy $policy, PolicyStatus $status): Policy
    {
        if (! $policy->status->canTransitionTo($status)) {
            throw new BusinessRuleException(
                "Não é possível alterar o status de {$policy->status->label()} para {$status->label()}.",
                'status',
            );
        }

        $this->ensureStatusFitsTerm($policy, $status);

        return DB::transaction(function () use ($policy, $status) {
            $policy->update(['status' => $status]);

            if (in_array($status, [PolicyStatus::Pending, PolicyStatus::Active], true) && ! $policy->installments()->exists()) {
                $this->generateInstallments($policy);
            }

            if ($status === PolicyStatus::Cancelled) {
                $policy->installments()
                    ->where('status', InstallmentStatus::Pending)
                    ->update(['status' => InstallmentStatus::Cancelled, 'updated_at' => now()]);
            }

            return $policy;
        });
    }

    public function delete(Policy $policy): void
    {
        if ($policy->status !== PolicyStatus::Quote) {
            throw new BusinessRuleException(
                'Somente cotações podem ser excluídas. Para encerrar uma apólice, altere o status para Cancelada.',
                'status',
            );
        }

        $policy->delete();
    }

    /**
     * Expira apólices ativas ou suspensas cuja vigência terminou antes de hoje.
     * Idempotente: apólices já expiradas não são selecionadas novamente.
     */
    public function expireEndedPolicies(): int
    {
        $expired = 0;

        Policy::query()
            ->whereIn('status', [PolicyStatus::Active, PolicyStatus::Suspended])
            ->where('ends_on', '<', today())
            ->lazyById()
            ->each(function (Policy $policy) use (&$expired) {
                $this->changeStatus($policy, PolicyStatus::Expired);
                $expired++;
            });

        return $expired;
    }

    private function ensureStatusFitsTerm(Policy $policy, PolicyStatus $status): void
    {
        if ($status === PolicyStatus::Active && $policy->ends_on->lt(today())) {
            throw new BusinessRuleException('Uma apólice com a vigência encerrada não pode estar ativa.', 'ends_on');
        }

        if ($status === PolicyStatus::Expired && $policy->ends_on->gte(today())) {
            throw new BusinessRuleException('A apólice só pode ser expirada após o fim da vigência.', 'status');
        }
    }

    private function generateInstallments(Policy $policy): void
    {
        try {
            $schedule = InstallmentSchedule::build($policy->premium, $policy->installments_count, $policy->starts_on);
        } catch (InvalidArgumentException) {
            throw new BusinessRuleException(
                'O prêmio é insuficiente para ser dividido na quantidade de parcelas informada.',
                'premium',
            );
        }

        $policy->installments()->createMany($schedule);
    }
}
