<?php

use App\Models\Article;
use App\Models\LocationType;
use App\Models\Machine;
use Database\Seeders\LocationTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('sitemap lists static pages and active database-backed pages only', function () {
    $this->seed(LocationTypeSeeder::class);
    $industry = LocationType::query()->where('show_on_industries', true)->firstOrFail();
    $machine = Machine::factory()->create(['is_active' => true]);
    $hiddenMachine = Machine::factory()->create(['is_active' => false]);
    $article = Article::factory()->create(['is_active' => true]);

    $response = $this->get(route('sitemap'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', false)
        ->assertSee('<loc>'.url('/').'/</loc>', false)
        ->assertSee('<loc>'.url('/machines').'</loc>', false)
        ->assertSee('<loc>'.url('/machines/'.$machine->slug).'</loc>', false)
        ->assertSee('<loc>'.url('/resources/'.$article->slug).'</loc>', false)
        ->assertSee('<loc>'.url('/industries/'.$industry->slug).'</loc>', false)
        ->assertDontSee('/machines/'.$hiddenMachine->slug, false);

    expect(simplexml_load_string($response->getContent()))->not->toBeFalse();
});

test('llms.txt describes the site and links every public page', function () {
    $machine = Machine::factory()->create(['is_active' => true]);

    $this->get(route('llms'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('# Vending Machine Locations For Sale')
        ->assertSee('## Main Navigation & Core Pages', false)
        ->assertSee('- [Home]('.url('/').'/)', false)
        ->assertSee('- ['.$machine->name.']('.url('/machines/'.$machine->slug).')', false);
});

test('robots.txt points crawlers at the sitemap', function () {
    expect(file_get_contents(public_path('robots.txt')))
        ->toContain('Sitemap: https://vendingmachinelocationsforsale.com/sitemap.xml');
});

test('every page ships the Google tag in the document head', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('https://www.googletagmanager.com/gtm.js?id=GTM-5MTWQBFZ', false);
});
