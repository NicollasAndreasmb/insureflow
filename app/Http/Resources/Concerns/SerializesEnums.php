<?php

namespace App\Http\Resources\Concerns;

use BackedEnum;

/**
 * Enums são expostos como {value, label}: o frontend usa value nas requisições
 * e filtros, e label para exibição, sem duplicar as traduções no Vue.
 */
trait SerializesEnums
{
    protected function enum(?BackedEnum $enum): ?array
    {
        if ($enum === null) {
            return null;
        }

        return ['value' => $enum->value, 'label' => $enum->label()];
    }
}
