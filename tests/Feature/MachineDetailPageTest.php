<?php

use App\Models\Machine;
use App\Models\MachineCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('machine detail page renders with database props', function () {
    $category = MachineCategory::factory()->create(['name' => 'Healthy']);

    $machine = Machine::factory()->create([
        'machine_category_id' => $category->id,
        'name' => 'Healthy Mix',
        'slug' => 'healthy',
        'capacity' => 'Up to 30 snack + 30 drink selections',
        'best_for' => 'Gyms, hospitals, schools and wellness programs',
        'dimensions' => null,
        'status' => 'Available nationwide',
        'sort_order' => 1,
    ]);

    Machine::factory()->count(3)->create([
        'machine_category_id' => $category->id,
    ]);

    Machine::factory()->inactive()->create([
        'machine_category_id' => $category->id,
        'slug' => 'inactive-related',
    ]);

    $this->get(route('machines.show', $machine->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('frontend/machines/show')
            ->has('site')
            ->where('machine.id', 'healthy')
            ->where('machine.name', 'Healthy Mix')
            ->where('machine.type', 'Healthy')
            ->where('status', 'Available nationwide')
            ->has('specs')
            ->where('specs.0.label', 'Capacity')
            ->where('specs.0.value', 'Up to 30 snack + 30 drink selections')
            ->has('checks', 4)
            ->has('related.items', 3)
            ->where('related.title', 'Other machines you might like.')
            ->where('cta.label', 'Request this machine')
            ->where('cta.href', '/#request-sec')
        );
});

test('the machine detail page shows the specifications image only when the machine has one', function () {
    $category = MachineCategory::factory()->create();

    $withSpecs = Machine::factory()->create([
        'machine_category_id' => $category->id,
        'slug' => 'ai-cooler',
        'spec_image' => '/images/machines/1000015236.jpg',
    ]);
    $withoutSpecs = Machine::factory()->create([
        'machine_category_id' => $category->id,
        'spec_image' => null,
    ]);

    $this->get(route('machines.show', $withSpecs->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('specsImage', '/images/machines/1000015236.jpg')
        );

    $this->get(route('machines.show', $withoutSpecs->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('specsImage', null)
        );
});

test('unknown machine detail returns 404', function () {
    $this->get(route('machines.show', 'not-a-machine'))
        ->assertNotFound();
});

test('inactive machine detail returns 404', function () {
    $machine = Machine::factory()->inactive()->create([
        'slug' => 'retired-machine',
    ]);

    $this->get(route('machines.show', $machine->slug))
        ->assertNotFound();
});
