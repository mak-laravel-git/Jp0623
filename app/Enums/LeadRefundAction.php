<?php

namespace App\Enums;

enum LeadRefundAction: string
{
    case RESELL = 'resell';
    case KEEP_OFF_SALE = 'keep_off_sale';

    public function label(): string
    {
        return match ($this) {
            self::RESELL => 'Sold again',
            self::KEEP_OFF_SALE => 'Kept off sale',
        };
    }
}
