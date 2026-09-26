<?php

namespace App\Enums;

enum PersonType: string
{
    case Individual = 'individual';
    case Company = 'company';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Pessoa física',
            self::Company => 'Pessoa jurídica',
        };
    }

    public function documentLength(): int
    {
        return match ($this) {
            self::Individual => 11,
            self::Company => 14,
        };
    }
}
