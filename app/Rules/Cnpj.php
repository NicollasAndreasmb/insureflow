<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida um CNPJ (somente dígitos) pelos dois dígitos verificadores.
 */
class Cnpj implements ValidationRule
{
    private const FIRST_WEIGHTS = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    private const SECOND_WEIGHTS = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isValid((string) $value)) {
            $fail('validation.cnpj')->translate();
        }
    }

    public static function isValid(string $cnpj): bool
    {
        if (! preg_match('/^\d{14}$/', $cnpj) || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        return (int) $cnpj[12] === self::checkDigit($cnpj, self::FIRST_WEIGHTS)
            && (int) $cnpj[13] === self::checkDigit($cnpj, self::SECOND_WEIGHTS);
    }

    private static function checkDigit(string $cnpj, array $weights): int
    {
        $sum = 0;

        foreach ($weights as $index => $weight) {
            $sum += (int) $cnpj[$index] * $weight;
        }

        $remainder = $sum % 11;

        return $remainder < 2 ? 0 : 11 - $remainder;
    }
}
