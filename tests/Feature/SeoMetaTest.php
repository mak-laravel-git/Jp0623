<?php

use App\Models\Machine;
use Database\Seeders\ArticleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Decodes the first JSON-LD block found in the rendered HTML.
 *
 * @return array<string, mixed>
 */
function jsonLdFrom(string $html): array
{
    preg_match('#<script data-inertia="schema" type="application/ld\+json">(.*?)</script>#s', $html, $matches);

    return json_decode($matches[1] ?? 'null', true, flags: JSON_THROW_ON_ERROR);
}

test('pages listed in config/seo.php render their title, description and canonical', function (string $path) {
    $seo = config('seo.pages.'.$path);

    $this->get($path)
        ->assertOk()
        ->assertSee('<title>'.e($seo['title']).'</title>', false)
        ->assertSee('<meta data-inertia="description" name="description" content="'.e($seo['description']).'">', false)
        ->assertSee('<link data-inertia="canonical" rel="canonical" href="'.rtrim(url('/'), '/').$path.'">', false);
})->with(['/', '/machines', '/products', '/micro-markets', '/office-coffee', '/schools', '/industries', '/cities', '/operators', '/resources', '/about', '/contact']);

test('the seo prop carries the canonical url without the query string', function () {
    $this->get('/machines?utm_source=newsletter')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('seo.canonical', url('/machines'))
            ->where('seo.title', config('seo.pages./machines.title'))
        );
});

test('pages missing from config/seo.php still get a self-referencing canonical', function () {
    $machine = Machine::factory()->create(['is_active' => true]);

    $this->get('/machines/'.$machine->slug)
        ->assertOk()
        ->assertSee('<link data-inertia="canonical" rel="canonical" href="'.url('/machines/'.$machine->slug).'">', false)
        ->assertInertia(fn (Assert $page) => $page->where('seo.title', null));
});

test('the homepage defines the organization and website that other schema nodes reference', function () {
    $schema = jsonLdFrom($this->get('/')->assertOk()->getContent());

    expect(collect($schema['@graph'])->pluck('@type')->all())->toBe(['Organization', 'WebSite'])
        ->and($schema['@graph'][0]['@id'])->toBe(url('/').'/#organization')
        ->and($schema['@graph'][1]['@id'])->toBe(url('/').'/#website');
});

test('the micro markets page ships its service schema', function () {
    $schema = jsonLdFrom($this->get('/micro-markets')->assertOk()->getContent());

    expect(collect($schema['@graph'])->pluck('@type')->all())->toBe(['WebPage', 'Service', 'BreadcrumbList'])
        ->and($schema['@graph'][1]['provider']['@id'])->toBe(url('/').'/#organization')
        ->and($schema['@graph'][2]['itemListElement'])->toHaveCount(2);
});

test('the ai vs market article ships its article schema', function () {
    $this->seed(ArticleSeeder::class);

    $response = $this->get('/resources/ai-vs-market')->assertOk();
    $schema = jsonLdFrom($response->getContent());

    expect($response->getContent())->toContain(e(config('seo.pages./resources/ai-vs-market.title')))
        ->and(collect($schema['@graph'])->pluck('@type')->all())->toBe(['BlogPosting', 'BreadcrumbList'])
        ->and($schema['@graph'][0]['@id'])->toBe(url('/resources/ai-vs-market').'#article')
        ->and($schema['@graph'][1]['itemListElement'])->toHaveCount(3);
});

test('non-public pages carry no seo data', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('seo', null));
});
