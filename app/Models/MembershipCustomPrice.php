<?php

namespace App\Models;

use Database\Factories\MembershipCustomPriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A monthly membership price agreed for one person, used instead of the default price.
 *
 * The price is kept by email so it can be set before the person has an account; `user_id`
 * is filled in as soon as the account exists.
 *
 * @property int $id
 * @property string $email
 * @property int $monthly_price
 * @property int|null $user_id
 * @property int|null $created_by
 * @property Carbon|null $notified_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $user
 */
class MembershipCustomPrice extends Model
{
    /** @use HasFactory<MembershipCustomPriceFactory> */
    use HasFactory;

    protected $fillable = [
        'email',
        'monthly_price',
        'user_id',
        'created_by',
        'notified_at',
    ];

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_price' => 'integer',
            'notified_at' => 'datetime',
        ];
    }
}
