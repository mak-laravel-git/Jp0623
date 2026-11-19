<?php

namespace App\Enums;

enum RouteInquiryStatus: string
{
    case PENDING = 'pending';
    case CONTACTED = 'contacted';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::CONTACTED => 'Contacted',
            self::CLOSED => 'Closed',
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
