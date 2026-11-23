<?php

namespace App\Enums;

enum LeadRefundType: string
{
    case DOLLAR = 'dollar';
    case CREDIT = 'credit';

    public function label(): string
    {
        return match ($this) {
            self::DOLLAR => 'Dollar refund',
            self::CREDIT => 'Credits',
        };
    }
}
