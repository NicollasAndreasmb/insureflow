<?php

namespace App\Http\Requests\Concerns;

/**
 * Normaliza campos antes da validação: documentos, telefones e CEP são
 * gravados somente com dígitos, e a UF em maiúsculas. Assim a API aceita tanto
 * "123.456.789-09" quanto "12345678909", e o banco guarda sempre o mesmo formato.
 */
trait NormalizesInput
{
    protected function keepDigits(string ...$fields): void
    {
        $normalized = [];

        foreach ($fields as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = preg_replace('/\D/', '', $this->input($field));
            }
        }

        $this->merge($normalized);
    }

    protected function uppercase(string ...$fields): void
    {
        $normalized = [];

        foreach ($fields as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = mb_strtoupper(trim($this->input($field)));
            }
        }

        $this->merge($normalized);
    }
}
