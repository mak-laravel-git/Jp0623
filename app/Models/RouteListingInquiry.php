<?php

namespace App\Models;

use App\Enums\RouteInquiryStatus;
use Database\Factories\RouteListingInquiryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $route_listing_id
 * @property int $user_id
 * @property string $contact_name
 * @property string $contact_email
 * @property string|null $contact_phone
 * @property string $listing_reference
 * @property string $listing_city
 * @property int $listing_asking_price
 * @property string $message
 * @property RouteInquiryStatus $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read RouteListing|null $routeListing
 * @property-read User $user
 */
class RouteListingInquiry extends Model
{
    /** @use HasFactory<RouteListingInquiryFactory> */
    use HasFactory;

    protected $fillable = [
        'route_listing_id',
        'user_id',
        'contact_name',
        'contact_email',
        'contact_phone',
        'listing_reference',
        'listing_city',
        'listing_asking_price',
        'message',
        'status',
    ];

    /**
     * @return BelongsTo<RouteListing, $this>
     */
    public function routeListing(): BelongsTo
    {
        return $this->belongsTo(RouteListing::class);
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
            'listing_asking_price' => 'integer',
            'status' => RouteInquiryStatus::class,
        ];
    }
}
