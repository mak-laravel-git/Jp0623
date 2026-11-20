<?php

use App\Enums\RoleEnum;
use App\Models\HeroImage;
use App\Models\HeroSection;
use App\Models\User;
use Database\Seeders\HeroSectionSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class, HeroSectionSeeder::class]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleEnum::SUPER_ADMIN->value);

    $this->hero = HeroSection::query()->with('images')->sole();
});

/**
 * Keeps every seeded image and applies the given overrides to the hero text.
 *
 * @return array<string, mixed>
 */
function heroPayload(HeroSection $hero, array $overrides = []): array
{
    return array_merge([
        'eyebrow' => $hero->eyebrow,
        'title_before' => $hero->title_before,
        'title_accent' => $hero->title_accent,
        'lede' => $hero->lede,
        'bullets' => $hero->bullets,
        'cta_label' => $hero->cta_label,
        'cta_href' => $hero->cta_href,
        'trust_stars' => $hero->trust_stars,
        'trust_text' => $hero->trust_text,
        'trust_reviews_label' => $hero->trust_reviews_label,
        'trust_reviews_href' => $hero->trust_reviews_href,
        'images' => $hero->images
            ->map(fn (HeroImage $image): array => ['id' => $image->id, 'alt' => $image->alt])
            ->all(),
    ], $overrides);
}

test('the seeder stores today\'s hero content and pictures', function () {
    expect($this->hero->eyebrow)->toBe('Vending management · Nationwide')
        ->and($this->hero->title_accent)->toBe('for every location.')
        ->and($this->hero->bullets)->toHaveCount(3)
        ->and($this->hero->cta_href)->toBe('#request-sec')
        ->and($this->hero->images)->toHaveCount(5)
        ->and($this->hero->images->first()->alt)->toBe('Snack and drink combo machine');
});

test('re-seeding never overwrites an edited hero', function () {
    $this->hero->update(['eyebrow' => 'Edited by admin']);
    $this->hero->images()->first()->delete();

    $this->seed(HeroSectionSeeder::class);

    expect(HeroSection::query()->count())->toBe(1)
        ->and($this->hero->refresh()->eyebrow)->toBe('Edited by admin')
        ->and($this->hero->images()->count())->toBe(4);
});

test('guests cannot access the hero section', function () {
    $this->get(route('admin.hero-section.edit'))->assertRedirect(route('login'));
});

test('members without permission cannot view or change the hero section', function () {
    $member = makeOperator();

    $this->actingAs($member)->get(route('admin.hero-section.edit'))->assertForbidden();
    $this->actingAs($member)
        ->put(route('admin.hero-section.update'), heroPayload($this->hero, ['eyebrow' => 'Hacked']))
        ->assertForbidden();

    expect($this->hero->refresh()->eyebrow)->toBe('Vending management · Nationwide');
});

test('admin can open the hero section editor', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.hero-section.edit'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('admin/hero-section/edit')
                ->where('heroSection.eyebrow', 'Vending management · Nationwide')
                ->has('heroSection.bullets', 3)
                ->has('heroSection.images', 5)
                ->where('heroSection.images.0.image_url', '/images/machines/snack-drink-combo.png')
        );
});

test('admin can update the hero text and bullets', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.hero-section.update'), heroPayload($this->hero, [
            'eyebrow' => 'Smart vending · Nationwide',
            'title_accent' => 'for every site.',
            'bullets' => ['One contact for every site', '  ', 'Free install'],
            'cta_label' => 'Get a free machine',
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->hero->refresh();

    expect($this->hero->eyebrow)->toBe('Smart vending · Nationwide')
        ->and($this->hero->title_accent)->toBe('for every site.')
        ->and($this->hero->cta_label)->toBe('Get a free machine')
        ->and($this->hero->bullets)->toBe(['One contact for every site', 'Free install'])
        ->and($this->hero->images)->toHaveCount(5);
});

test('admin can add a new picture and it is stored', function () {
    Storage::fake('public');

    $payload = heroPayload($this->hero);
    $payload['images'][] = ['alt' => 'Sandstar AI cooler', 'image' => UploadedFile::fake()->image('cooler.jpg')];

    $this->actingAs($this->admin)
        ->put(route('admin.hero-section.update'), $payload)
        ->assertSessionHasNoErrors();

    $added = $this->hero->images()->get()->last();

    expect($this->hero->images()->count())->toBe(6)
        ->and($added->alt)->toBe('Sandstar AI cooler')
        ->and($added->sort_order)->toBe(5)
        ->and($added->usesStoredUpload())->toBeTrue();
    Storage::disk('public')->assertExists($added->image);
});

test('replacing an uploaded picture deletes the old file', function () {
    Storage::fake('public');

    $oldPath = UploadedFile::fake()->image('old.jpg')->store('hero', 'public');
    $uploaded = HeroImage::factory()->uploaded($oldPath)->create(['hero_section_id' => $this->hero->id, 'sort_order' => 9]);

    $payload = heroPayload($this->hero->refresh());
    $payload['images'][5]['image'] = UploadedFile::fake()->image('new.jpg');

    $this->actingAs($this->admin)
        ->put(route('admin.hero-section.update'), $payload)
        ->assertSessionHasNoErrors();

    $uploaded->refresh();

    expect($uploaded->image)->not->toBe($oldPath);
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($uploaded->image);
});

test('removing a picture deletes its uploaded file but never the default images', function () {
    Storage::fake('public');

    $path = UploadedFile::fake()->image('extra.jpg')->store('hero', 'public');
    HeroImage::factory()->uploaded($path)->create(['hero_section_id' => $this->hero->id, 'sort_order' => 9]);

    $payload = heroPayload($this->hero->refresh());
    array_pop($payload['images']);
    array_shift($payload['images']);

    $this->actingAs($this->admin)
        ->put(route('admin.hero-section.update'), $payload)
        ->assertSessionHasNoErrors();

    expect($this->hero->images()->count())->toBe(4);
    Storage::disk('public')->assertMissing($path);
    expect(public_path('images/machines/snack-drink-combo.png'))->toBeFile();
});

test('pictures follow the submitted order', function () {
    $payload = heroPayload($this->hero);
    $payload['images'] = array_reverse($payload['images']);

    $this->actingAs($this->admin)
        ->put(route('admin.hero-section.update'), $payload)
        ->assertSessionHasNoErrors();

    expect($this->hero->images()->pluck('alt')->all())
        ->toBe(array_column($payload['images'], 'alt'));
});

test('the hero requires its text, at least one picture and an alt text', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.hero-section.update'), heroPayload($this->hero, [
            'eyebrow' => '',
            'bullets' => [],
            'images' => [],
        ]))
        ->assertSessionHasErrors(['eyebrow', 'bullets', 'images']);

    $payload = heroPayload($this->hero);
    $payload['images'][0]['alt'] = '';

    $this->actingAs($this->admin)
        ->put(route('admin.hero-section.update'), $payload)
        ->assertSessionHasErrors(['images.0.alt']);

    expect($this->hero->refresh()->eyebrow)->toBe('Vending management · Nationwide');
});

test('a new picture needs a file and uploads must be images', function () {
    Storage::fake('public');

    $payload = heroPayload($this->hero);
    $payload['images'][] = ['alt' => 'No file'];

    $this->actingAs($this->admin)
        ->put(route('admin.hero-section.update'), $payload)
        ->assertSessionHasErrors(['images.5.image']);

    $payload['images'][5] = ['alt' => 'Not an image', 'image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')];

    $this->actingAs($this->admin)
        ->put(route('admin.hero-section.update'), $payload)
        ->assertSessionHasErrors(['images.5.image']);

    expect($this->hero->images()->count())->toBe(5);
});
