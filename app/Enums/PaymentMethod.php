<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case BankSlip = 'bank_slip';
    case CreditCard = 'credit_card';
    case DirectDebit = 'direct_debit';
    case Pix = 'pix';

    public function label(): string
    {
        return match ($this) {
            self::BankSlip => 'Boleto',
            self::CreditCard => 'Cartão de crédito',
            self::DirectDebit => 'Débito em conta',
            self::Pix => 'Pix',
        };
    }
}
