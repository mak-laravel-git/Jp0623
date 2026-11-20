<?php

namespace App\Services;

use App\Models\HeroImage;
use App\Models\HeroSection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HeroSectionService
{
    /**
     * Where uploaded hero pictures are stored on the public disk.
     */
    private const IMAGE_DIRECTORY = 'hero';

    /**
     * The hero row for the admin form, created empty when the seeder has not run yet.
     */
    public function current(): HeroSection
    {
        $hero = HeroSection::query()->with('images')->first();

        if ($hero === null) {
            $hero = HeroSection::create($this->blankAttributes());
            $hero->load('images');
        }

        return $hero;
    }

    /**
     * The hero in the shape the home page renders. An empty hero is returned when no row exists yet.
     *
     * @return array{eyebrow: string, titleBefore: string, titleAccent: string, lede: string, bullets: list<string>, cta: array{label: string, href: string}, trust: array{stars: string, text: string, reviewsLabel: string, reviewsHref: string}, images: list<array{src: string, alt: string}>}
     */
    public function forFrontend(): array
    {
        $hero = HeroSection::query()->with('images')->first();

        if ($hero === null) {
            $hero = new HeroSection($this->blankAttributes());
            $hero->setRelation('images', collect());
        }

        return [
            'eyebrow' => $hero->eyebrow,
            'titleBefore' => $hero->title_before,
            'titleAccent' => $hero->title_accent,
            'lede' => $hero->lede,
            'bullets' => array_values($hero->bullets ?? []),
            'cta' => [
                'label' => $hero->cta_label,
                'href' => $hero->cta_href,
            ],
            'trust' => [
                'stars' => $hero->trust_stars,
                'text' => $hero->trust_text,
                'reviewsLabel' => $hero->trust_reviews_label,
                'reviewsHref' => $hero->trust_reviews_href,
            ],
            'images' => $hero->images
                ->filter(fn (HeroImage $image): bool => filled($image->image_url))
                ->map(fn (HeroImage $image): array => [
                    'src' => (string) $image->image_url,
                    'alt' => $image->alt,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Save the hero text and sync its pictures to the submitted list (order, alt text, new uploads, removals).
     *
     * @param  array<string, mixed>  $data  Validated request data; `images` items carry an optional `id`, `alt` and `image` upload.
     */
    public function update(HeroSection $hero, array $data): HeroSection
    {
        /** @var list<array{id?: int|string|null, alt: string, image?: UploadedFile|null}> $images */
        $images = array_values($data['images'] ?? []);
        unset($data['images']);

        $data['bullets'] = array_values(array_filter(
            array_map(fn (mixed $bullet): string => trim((string) $bullet), $data['bullets'] ?? []),
            fn (string $bullet): bool => $bullet !== '',
        ));

        DB::transaction(function () use ($hero, $data, $images): void {
            $hero->update($data);
            $this->syncImages($hero, $images);
        });

        return $hero->refresh()->load('images');
    }

    /**
     * @param  list<array{id?: int|string|null, alt: string, image?: UploadedFile|null}>  $items
     */
    private function syncImages(HeroSection $hero, array $items): void
    {
        $existing = $hero->images()->get()->keyBy('id');

        $keptIds = collect($items)
            ->pluck('id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $existing
            ->reject(fn (HeroImage $image): bool => in_array($image->id, $keptIds, true))
            ->each(function (HeroImage $image): void {
                $this->deleteStoredImage($image);
                $image->delete();
            });

        foreach ($items as $index => $item) {
            $file = ($item['image'] ?? null) instanceof UploadedFile ? $item['image'] : null;
            $image = $existing->get((int) ($item['id'] ?? 0));

            if ($image === null) {
                if ($file === null) {
                    continue;
                }

                $hero->images()->create([
                    'image' => $file->store(self::IMAGE_DIRECTORY, 'public'),
                    'alt' => $item['alt'],
                    'sort_order' => $index,
                ]);

                continue;
            }

            if ($file !== null) {
                $this->deleteStoredImage($image);
                $image->image = $file->store(self::IMAGE_DIRECTORY, 'public');
            }

            $image->alt = $item['alt'];
            $image->sort_order = $index;
            $image->save();
        }
    }

    private function deleteStoredImage(HeroImage $image): void
    {
        if ($image->usesStoredUpload()) {
            Storage::disk('public')->delete($image->image);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function blankAttributes(): array
    {
        return [
            'eyebrow' => '',
            'title_before' => '',
            'title_accent' => '',
            'lede' => '',
            'bullets' => [],
            'cta_label' => '',
            'cta_href' => '',
            'trust_stars' => '',
            'trust_text' => '',
            'trust_reviews_label' => '',
            'trust_reviews_href' => '',
        ];
    }
}
