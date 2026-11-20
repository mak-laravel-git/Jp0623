<?php

namespace App\Models;

use Database\Factories\HeroSectionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The home page hero. There is a single row, edited from the admin dashboard.
 *
 * @property int $id
 * @property string $eyebrow
 * @property string $title_before
 * @property string $title_accent
 * @property string $lede
 * @property list<string> $bullets
 * @property string $cta_label
 * @property string $cta_href
 * @property string $trust_stars
 * @property string $trust_text
 * @property string $trust_reviews_label
 * @property string $trust_reviews_href
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, HeroImage> $images
 */
class HeroSection extends Model
{
    /** @use HasFactory<HeroSectionFactory> */
    use HasFactory;

    protected $fillable = [
        'eyebrow',
        'title_before',
        'title_accent',
        'lede',
        'bullets',
        'cta_label',
        'cta_href',
        'trust_stars',
        'trust_text',
        'trust_reviews_label',
        'trust_reviews_href',
    ];

    /**
     * @return HasMany<HeroImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(HeroImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bullets' => 'array',
        ];
    }
}
