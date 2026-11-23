<?php

namespace App\Models;

use App\Enums\LeadRefundAction;
use App\Enums\LeadRefundStatus;
use App\Enums\LeadRefundType;
use Database\Factories\LeadRefundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The refund the admin gave for an approved lead return (history record).
 *
 * @property int $id
 * @property int $lead_issue_report_id
 * @property int $lead_purchase_id
 * @property int $user_id
 * @property int|null $refunded_by
 * @property LeadRefundType $type
 * @property int $total_amount
 * @property int $credit_amount Dollars given back as Credits.
 * @property int $card_amount Dollars sent back to the card.
 * @property LeadRefundStatus $status
 * @property string|null $square_refund_id
 * @property string|null $square_status
 * @property string $idempotency_key
 * @property string|null $failure_message
 * @property LeadRefundAction $lead_action
 * @property string|null $admin_note
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read LeadIssueReport $report
 * @property-read LeadPurchase $leadPurchase
 * @property-read User $user
 * @property-read User|null $refundedBy
 */
class LeadRefund extends Model
{
    /** @use HasFactory<LeadRefundFactory> */
    use HasFactory;

    protected $fillable = [
        'lead_issue_report_id',
        'lead_purchase_id',
        'user_id',
        'refunded_by',
        'type',
        'total_amount',
        'credit_amount',
        'card_amount',
        'status',
        'square_refund_id',
        'square_status',
        'idempotency_key',
        'failure_message',
        'lead_action',
        'admin_note',
    ];

    /**
     * @return BelongsTo<LeadIssueReport, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(LeadIssueReport::class, 'lead_issue_report_id');
    }

    /**
     * @return BelongsTo<LeadPurchase, $this>
     */
    public function leadPurchase(): BelongsTo
    {
        return $this->belongsTo(LeadPurchase::class);
    }

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
    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LeadRefundType::class,
            'status' => LeadRefundStatus::class,
            'lead_action' => LeadRefundAction::class,
            'total_amount' => 'integer',
            'credit_amount' => 'integer',
            'card_amount' => 'integer',
        ];
    }
}
