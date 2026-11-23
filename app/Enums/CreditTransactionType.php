<?php

namespace App\Enums;

enum CreditTransactionType: string
{
    case REFUND_CREDIT = 'refund_credit';
    case PURCHASE_SPEND = 'purchase_spend';
    case PURCHASE_RESTORE = 'purchase_restore';

    public function label(): string
    {
        return match ($this) {
            self::REFUND_CREDIT => 'Refund credited',
            self::PURCHASE_SPEND => 'Used for a lead',
            self::PURCHASE_RESTORE => 'Credits returned',
        };
    }
}
