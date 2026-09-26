<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Divide o prêmio de uma apólice em parcelas mensais.
 *
 * O cálculo é feito em centavos inteiros para evitar erros de ponto flutuante.
 * A diferença de arredondamento vai para a primeira parcela, de modo que a
 * soma das parcelas é sempre exatamente igual ao prêmio (ex.: 1000,00 em 3x =
 * 333,34 + 333,33 + 333,33). Os vencimentos são mensais a partir do primeiro,
 * sem "transbordar" o mês (31/01 -> 28/02 -> 31/03).
 *
 * É uma classe pura, sem acesso ao banco: usada pelos seeders e pelo serviço de
 * apólices, que é quem persiste as parcelas.
 */
final class InstallmentSchedule
{
    /**
     * @return list<array{number: int, amount: string, due_date: CarbonImmutable}>
     */
    public static function build(string $premium, int $count, CarbonInterface $firstDueDate): array
    {
        if ($count < 1) {
            throw new InvalidArgumentException('Installment count must be at least 1.');
        }

        $totalCents = (int) bcmul($premium, '100', 0);

        if ($totalCents < $count) {
            throw new InvalidArgumentException('Premium is too low to be split into the requested installments.');
        }

        $baseCents = intdiv($totalCents, $count);
        $remainderCents = $totalCents - ($baseCents * $count);
        $firstDueDate = CarbonImmutable::parse($firstDueDate)->startOfDay();

        $installments = [];

        for ($index = 0; $index < $count; $index++) {
            $cents = $index === 0 ? $baseCents + $remainderCents : $baseCents;

            $installments[] = [
                'number' => $index + 1,
                'amount' => bcdiv((string) $cents, '100', 2),
                'due_date' => $firstDueDate->addMonthsNoOverflow($index),
            ];
        }

        return $installments;
    }
}
