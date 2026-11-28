<?php

namespace App\Models;

use Database\Factories\TwilioSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The single row of Twilio SMS settings edited by the admin. Anything left empty falls back to `.env`.
 * The auth token is encrypted with APP_KEY.
 *
 * @property int $id
 * @property bool|null $enabled
 * @property string|null $sid
 * @property string|null $auth_token
 * @property string|null $from_number
 * @property string|null $messaging_service_sid
 * @property int|null $lead_radius_miles
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $updater
 */
class TwilioSetting extends Model
{
    /** @use HasFactory<TwilioSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'enabled',
        'sid',
        'auth_token',
        'from_number',
        'messaging_service_sid',
        'lead_radius_miles',
        'updated_by',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'auth_token',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'auth_token' => 'encrypted',
            'lead_radius_miles' => 'integer',
        ];
    }
}
