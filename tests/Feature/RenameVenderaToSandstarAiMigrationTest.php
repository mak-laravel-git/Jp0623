<?php

use App\Models\Machine;
use App\Models\MachineCategory;
use App\Models\OperatorProfile;
use App\Models\Review;
use App\Models\VendingRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function runVenderaRenameMigration(): void
{
    $migration = require database_path('migrations/2026_11_19_054902_rename_vendera_to_sandstar_ai.php');

    $migration->up();
}

test('stored Vendera content is renamed to Sandstar AI', function () {
    $category = MachineCategory::factory()->create(['name' => 'Vendera cooler', 'slug' => 'vendera-cooler']);
    $machine = Machine::factory()->create([
        'machine_category_id' => $category->id,
        'name' => 'Vendera Smart Cooler',
        'tags' => ['Vendera cooler', 'Tap and go'],
        'description' => 'A Vendera smart cooler for small teams.',
        'image' => '/images/machines/Vendera-Tap-Grab-Go-Cabinet.png',
    ]);
    $request = VendingRequest::factory()->create(['machine_interests' => ['Snack', 'Vendera cooler']]);
    $profile = OperatorProfile::factory()->create(['machine_types' => ['Combo', 'Vendera cooler']]);
    $review = Review::factory()->create(['quote' => 'The Vendera cooler was a hit with our night shift.']);

    runVenderaRenameMigration();

    expect($category->refresh()->name)->toBe('Sandstar AI cooler')
        ->and($category->slug)->toBe('sandstar-ai-cooler')
        ->and($machine->refresh()->name)->toBe('Sandstar AI Cooler')
        ->and($machine->tags)->toBe(['Sandstar AI cooler', 'Tap and go'])
        ->and($machine->description)->toBe('A Sandstar AI cooler for small teams.')
        ->and($request->refresh()->machine_interests)->toBe(['Snack', 'Sandstar AI cooler'])
        ->and($profile->refresh()->machine_types)->toBe(['Combo', 'Sandstar AI cooler'])
        ->and($review->refresh()->quote)->toBe('The Sandstar AI cooler was a hit with our night shift.');
});

test('image file names and unrelated content are left untouched', function () {
    $machine = Machine::factory()->create([
        'name' => 'Space Saver Combo',
        'description' => 'A slim unit that fits where a full-size machine will not.',
        'image' => '/images/machines/Vendera-Tap-Grab-Go-Cabinet.png',
    ]);

    runVenderaRenameMigration();

    $machine->refresh();

    expect($machine->name)->toBe('Space Saver Combo')
        ->and($machine->description)->toBe('A slim unit that fits where a full-size machine will not.')
        ->and($machine->image)->toBe('/images/machines/Vendera-Tap-Grab-Go-Cabinet.png');
});

test('the category slug is kept when the new slug already exists', function () {
    MachineCategory::factory()->create(['name' => 'Sandstar AI cooler', 'slug' => 'sandstar-ai-cooler']);
    $old = MachineCategory::factory()->create(['name' => 'Vendera cooler', 'slug' => 'vendera-cooler']);

    runVenderaRenameMigration();

    expect($old->refresh()->slug)->toBe('vendera-cooler');
});
