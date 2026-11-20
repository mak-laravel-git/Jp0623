<?php

namespace App\Models;

use Database\Factories\HeroImageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $hero_section_id
 * @property string $image
 * @property string $alt
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read string|null $image_url
 */
class HeroImage extends Model
{
    /** @use HasFactory<HeroImageFactory> */
    use HasFactory;

    protected $fillable = [
        'hero_section_id',
        'image',
        'alt',
        'sort_order',
    ];

    protected $appends = ['image_url'];

    /**
     * @return BelongsTo<HeroSection, $this>
     */
    public function heroSection(): BelongsTo
    {
        return $this->belongsTo(HeroSection::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        if ($this->image === null || $this->image === '') {
            return null;
        }

        if (str_starts_with($this->image, '/') || str_starts_with($this->image, 'http')) {
            return $this->image;
        }

        return '/storage/'.$this->image;
    }

    /**
     * True for files uploaded from the admin; the default `/images/...` files shipped with the app are never deleted.
     */
    public function usesStoredUpload(): bool
    {
        if ($this->image === null || $this->image === '') {
            return false;
        }

        return ! str_starts_with($this->image, '/') && ! str_starts_with($this->image, 'http');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
