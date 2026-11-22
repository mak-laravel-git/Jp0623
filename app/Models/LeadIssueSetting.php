<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The single row of settings for returning purchased leads, edited by the admin.
 *
 * @property int $id
 * @property int|null $report_window_days Days after buying a lead that it can still be sent back; null = no limit.
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class LeadIssueSetting extends Model
{
    protected $fillable = [
        'report_window_days',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'report_window_days' => 'integer',
        ];
    }
}
