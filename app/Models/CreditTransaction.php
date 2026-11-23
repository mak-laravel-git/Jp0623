<?php

namespace App\Models;

use App\Enums\CreditTransactionType;
use Database\Factories\CreditTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One line of a member's Credits history. `amount` is signed (+ earned, - spent).
 *
 * @property int $id
 * @property int $user_id
 * @property CreditTransactionType $type
 * @property int $amount
 * @property int $balance_after
 * @property int|null $lead_purchase_id
 * @property int|null $lead_issue_report_id
 * @property int|null $created_by
 * @property string|null $note
 * @property Carbon $created_at
 */
class CreditTransaction extends Model
{
    /** @use HasFactory<CreditTransactionFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'balance_after',
        'lead_purchase_id',
        'lead_issue_report_id',
        'created_by',
        'note',
    ];

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
            'type' => CreditTransactionType::class,
            'amount' => 'integer',
            'balance_after' => 'integer',
        ];
    }
}
