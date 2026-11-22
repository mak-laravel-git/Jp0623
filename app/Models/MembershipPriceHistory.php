<?php

namespace App\Models;

use Database\Factories\MembershipPriceHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One entry in the log of custom membership price changes (append-only).
 *
 * For a `removed` entry, `new_price` is the default price the member pays from then on.
 *
 * @property int $id
 * @property string $email
 * @property int|null $user_id
 * @property string $action
 * @property int|null $old_price
 * @property int|null $new_price
 * @property int|null $changed_by
 * @property bool $email_sent
 * @property Carbon $created_at
 * @property-read User|null $user
 * @property-read User|null $changedBy
 */
class MembershipPriceHistory extends Model
{
    /** @use HasFactory<MembershipPriceHistoryFactory> */
    use HasFactory;

    public const ACTION_CREATED = 'created';

    public const ACTION_UPDATED = 'updated';

    public const ACTION_REMOVED = 'removed';

    public const UPDATED_AT = null;

    protected $fillable = [
        'email',
        'user_id',
        'action',
        'old_price',
        'new_price',
        'changed_by',
        'email_sent',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_price' => 'integer',
            'new_price' => 'integer',
            'email_sent' => 'boolean',
        ];
    }
}
