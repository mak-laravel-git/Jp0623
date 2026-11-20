<?php

use App\Models\HeroImage;
use App\Models\HeroSection;
use App\Models\LocationType;
use App\Models\Machine;
use App\Models\MachineCategory;
use Database\Seeders\HeroSectionSeeder;
use Database\Seeders\MachineCategorySeeder;
use Database\Seeders\MachineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

function runVenderaPicturesMigration(): void
{
    $migration = require database_path('migrations/2026_11_20_044001_replace_vendera_pictures_with_sandstar_ai_photos.php');

    $migration->up();
}

test('the seeded Sandstar AI cooler uses the client photo and specifications image', function () {
    $this->seed([MachineCategorySeeder::class, MachineSeeder::class]);

    $cooler = Machine::query()->where('slug', 'ai-cooler')->sole();

    expect($cooler->image)->toBe('/images/machines/1000015229.jpg')
        ->and($cooler->spec_image)->toBe('/images/machines/1000015236.jpg')
        ->and(Machine::query()->where('slug', '!=', 'ai-cooler')->whereNotNull('spec_image')->count())->toBe(0);
});

test('every picture the seeders reference exists and none is a Vendera picture', function () {
    $this->seed([MachineCategorySeeder::class, MachineSeeder::class, HeroSectionSeeder::class]);

    $paths = Machine::query()->pluck('image')
        ->merge(Machine::query()->whereNotNull('spec_image')->pluck('spec_image'))
        ->merge(HeroImage::query()->pluck('image'))
        ->filter()
        ->unique();

    expect($paths)->not->toBeEmpty();

    foreach ($paths as $path) {
        expect($path)->not->toContain('Vendera')
            ->and(public_path(ltrim($path, '/')))->toBeFile();
    }
});

test('the Vendera picture files are gone from the public folder', function () {
    expect(glob(public_path('images/*/*Vendera*')))->toBeEmpty();

    foreach ([
        'images/machines/Alpine-ST5000.png',
        'images/machines/healthy-vending.png',
        'images/machines/coffee.png',
        'images/hero/jp0623-attachments/1000014854.jpg',
        'images/hero/jp0623-attachments/1000014856.jpg',
    ] as $removed) {
        expect(public_path($removed))->not->toBeFile();
    }
});

test('every site picture referenced by seeders, demo data and components exists', function () {
    $missing = [];

    foreach ([app_path('Demo'), database_path('seeders'), resource_path('js/components'), resource_path('js/pages')] as $directory) {
        foreach (File::allFiles($directory) as $file) {
            preg_match_all('#[\'"](/images/[^\'"\s]+\.(?:png|jpe?g|webp|svg))[\'"]#', $file->getContents(), $matches);

            foreach ($matches[1] as $path) {
                if (! is_file(public_path(ltrim($path, '/')))) {
                    $missing[] = $file->getRelativePathname().' -> '.$path;
                }
            }
        }
    }

    expect($missing)->toBeEmpty();
});

test('stored pictures that carry the Vendera logo are replaced by clean ones', function () {
    $migration = require database_path('migrations/2026_11_20_045305_replace_logo_bearing_vendera_pictures.php');

    $alpine = Machine::factory()->create(['image' => '/images/machines/Alpine-ST5000.png']);
    $uploaded = Machine::factory()->create(['image' => 'machines/admin-upload.jpg']);
    $combo = Machine::factory()->create(['image' => '/images/machines/snack-drink-combo.png']);
    $hero = HeroImage::factory()->create(['image' => '/images/machines/Alpine-ST5000.png']);
    $healthy = LocationType::factory()->create(['image' => '/images/machines/healthy-vending.png']);

    $migration->up();

    expect($alpine->refresh()->image)->toBe('/images/machines/Alpine-ST50000.jpg')
        ->and($hero->refresh()->image)->toBe('/images/machines/Alpine-ST50000.jpg')
        ->and($healthy->refresh()->image)->toBe('/images/machines/healthy-vending1.png')
        ->and($uploaded->refresh()->image)->toBe('machines/admin-upload.jpg')
        ->and($combo->refresh()->image)->toBe('/images/machines/snack-drink-combo.png');
});

test('stored Vendera pictures are replaced and the cooler gets its specifications image', function () {
    $category = MachineCategory::factory()->create();
    $cooler = Machine::factory()->create([
        'machine_category_id' => $category->id,
        'slug' => 'ai-cooler',
        'image' => '/images/machines/Vendera-Tap-Grab-Go-Cabinet.png',
        'spec_image' => null,
    ]);
    $uploaded = Machine::factory()->create([
        'machine_category_id' => $category->id,
        'image' => 'machines/admin-upload.jpg',
    ]);

    $hero = HeroSection::factory()->create();
    $combo = HeroImage::factory()->create(['hero_section_id' => $hero->id, 'alt' => 'Snack and drink combo machine', 'image' => '/images/hero/Vendera-Black-Glass-Display-Cooler.png']);
    $alpine = HeroImage::factory()->create(['hero_section_id' => $hero->id, 'alt' => 'Alpine snack and beverage machine', 'image' => '/images/machines/Vendera-Tap-Grab-Go-Cabinet.png']);
    $sandstar = HeroImage::factory()->create(['hero_section_id' => $hero->id, 'alt' => 'Sandstar AI cooler', 'image' => '/images/machines/Vendera-Tap-Grab-Go-Cabinet.png']);
    $traditional = HeroImage::factory()->create(['hero_section_id' => $hero->id, 'alt' => 'Traditional vending machine', 'image' => '/images/hero/tradational-vending.png']);

    runVenderaPicturesMigration();

    expect($cooler->refresh()->image)->toBe('/images/machines/1000015229.jpg')
        ->and($cooler->spec_image)->toBe('/images/machines/1000015236.jpg')
        ->and($uploaded->refresh()->image)->toBe('machines/admin-upload.jpg')
        ->and($combo->refresh()->image)->toBe('/images/machines/snack-drink-combo.png')
        ->and($alpine->refresh()->image)->toBe('/images/machines/Alpine-ST50000.jpg')
        ->and($sandstar->refresh()->image)->toBe('/images/machines/1000015229.jpg')
        ->and($traditional->refresh()->image)->toBe('/images/hero/tradational-vending.png');
});

test('an admin-chosen specifications image on the cooler is not overwritten', function () {
    $cooler = Machine::factory()->create([
        'slug' => 'ai-cooler',
        'image' => 'machines/admin-upload.jpg',
        'spec_image' => 'machines/admin-specs.jpg',
    ]);

    runVenderaPicturesMigration();

    expect($cooler->refresh()->spec_image)->toBe('machines/admin-specs.jpg');
});
