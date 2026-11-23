<?php

namespace App\Models;

use App\Enums\LeadIssueCategory;
use App\Enums\LeadIssueStatus;
use Database\Factories\LeadIssueReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * An operator sending a purchased lead back to the admin with a written reason.
 *
 * The lead details are copied onto the report so the admin list stays readable
 * whatever happens to the lead later.
 *
 * @property int $id
 * @property int $lead_purchase_id
 * @property int $user_id
 * @property LeadIssueCategory $category
 * @property string $reason
 * @property LeadIssueStatus $status
 * @property string|null $admin_note
 * @property int|null $resolved_by
 * @property Carbon|null $resolved_at
 * @property string $lead_reference
 * @property string|null $lead_name
 * @property string|null $lead_type
 * @property string|null $lead_city
 * @property int $price
 * @property Carbon|null $purchased_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read LeadPurchase $leadPurchase
 * @property-read User $user
 * @property-read User|null $resolver
 */
class LeadIssueReport extends Model
{
    /** @use HasFactory<LeadIssueReportFactory> */
    use HasFactory;

    protected $fillable = [
        'lead_purchase_id',
        'user_id',
        'category',
        'reason',
        'status',
        'admin_note',
        'resolved_by',
        'resolved_at',
        'lead_reference',
        'lead_name',
        'lead_type',
        'lead_city',
        'price',
        'purchased_at',
    ];

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
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * @return HasOne<LeadRefund, $this>
     */
    public function refund(): HasOne
    {
        return $this->hasOne(LeadRefund::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => LeadIssueCategory::class,
            'status' => LeadIssueStatus::class,
            'price' => 'integer',
            'resolved_at' => 'datetime',
            'purchased_at' => 'datetime',
        ];
    }
}
