<?php

namespace App\Models;

use Database\Factories\RouteListingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $city
 * @property string $about
 * @property int $locations_count
 * @property int $machines_count
 * @property int $annual_revenue
 * @property int $asking_price
 * @property bool $seller_financing
 * @property bool $is_active
 * @property Carbon $listed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class RouteListing extends Model
{
    /** @use HasFactory<RouteListingFactory> */
    use HasFactory;

    protected $fillable = [
        'city',
        'about',
        'locations_count',
        'machines_count',
        'annual_revenue',
        'asking_price',
        'seller_financing',
        'is_active',
        'listed_at',
    ];

    /**
     * @return HasMany<RouteListingInquiry, $this>
     */
    public function inquiries(): HasMany
    {
        return $this->hasMany(RouteListingInquiry::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'locations_count' => 'integer',
            'machines_count' => 'integer',
            'annual_revenue' => 'integer',
            'asking_price' => 'integer',
            'seller_financing' => 'boolean',
            'is_active' => 'boolean',
            'listed_at' => 'date:Y-m-d',
        ];
    }
}
