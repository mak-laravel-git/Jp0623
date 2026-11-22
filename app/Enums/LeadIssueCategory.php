<?php

namespace App\Enums;

enum LeadIssueCategory: string
{
    case FAKE_LEAD = 'fake_lead';
    case CANNOT_SERVE = 'cannot_serve';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::FAKE_LEAD => 'Fake or wrong lead',
            self::CANNOT_SERVE => 'I could not provide the service',
            self::OTHER => 'Other',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $category): string => $category->value, self::cases());
    }
}
