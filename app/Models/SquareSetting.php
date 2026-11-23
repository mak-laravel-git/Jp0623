<?php

namespace App\Models;

use Database\Factories\SquareSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The single row of Square payment settings edited by the admin. Anything left empty falls back to `.env`.
 * The access token and the webhook signature key are encrypted with APP_KEY.
 *
 * @property int $id
 * @property string|null $environment sandbox or production
 * @property string|null $application_id
 * @property string|null $location_id
 * @property string|null $webhook_url
 * @property string|null $access_token
 * @property string|null $webhook_signature_key
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $updater
 */
class SquareSetting extends Model
{
    /** @use HasFactory<SquareSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'environment',
        'application_id',
        'location_id',
        'webhook_url',
        'access_token',
        'webhook_signature_key',
        'updated_by',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'access_token',
        'webhook_signature_key',
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
            'access_token' => 'encrypted',
            'webhook_signature_key' => 'encrypted',
        ];
    }
}
