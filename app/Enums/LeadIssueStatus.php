<?php

namespace App\Enums;

enum LeadIssueStatus: string
{
    case OPEN = 'open';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::APPROVED => 'Return approved',
            self::REJECTED => 'Return rejected',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status): string => $status->value, self::cases());
    }
}
