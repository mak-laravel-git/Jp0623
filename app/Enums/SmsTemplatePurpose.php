<?php

namespace App\Enums;

enum SmsTemplatePurpose: string
{
    case AUTO_VENDING = 'auto_vending';
    case AUTO_COFFEE = 'auto_coffee';
    case MANUAL = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::AUTO_VENDING => 'Auto — Vending form (nearby operators)',
            self::AUTO_COFFEE => 'Auto — Coffee form (nearby operators)',
            self::MANUAL => 'Manual send (Operator SMS)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::AUTO_VENDING => 'Sent automatically to nearby operators when a vending request is submitted.',
            self::AUTO_COFFEE => 'Sent automatically to nearby operators when a coffee request is submitted.',
            self::MANUAL => 'Available as a ready-made option when an admin sends SMS manually.',
        };
    }
}
