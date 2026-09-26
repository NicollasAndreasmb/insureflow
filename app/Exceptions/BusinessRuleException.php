<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Violação de uma regra de negócio (ex.: transição de status inválida,
 * sinistro fora da vigência).
 *
 * Lançada pelos Services, que não conhecem HTTP. É renderizada como 422 no
 * mesmo formato de um erro de validação ({message, errors: {campo: [...]}}),
 * para que o frontend trate os dois casos com o mesmo código.
 */
class BusinessRuleException extends RuntimeException
{
    public function __construct(string $message, public readonly string $field = 'general')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [$this->field => [$this->getMessage()]],
        ], 422);
    }
}
