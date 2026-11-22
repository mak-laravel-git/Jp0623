<?php

namespace App\Services;

use App\Models\Article;
use App\Models\LegalPage;
use App\Models\LocationType;
use App\Models\Machine;
use Carbon\CarbonInterface;

/**
 * Builds the list of public pages shared by sitemap.xml and llms.txt.
 *
 * @phpstan-type SeoPage array{path: string, title: string, description: string, lastmod: ?string, changefreq: string, priority: string}
 */
class SeoPageService
{
    /**
     * Static public pages: path, title, description, changefreq and priority.
     *
     * @var list<array{path: string, title: string, description: string, changefreq: string, priority: string}>
     */
    private const STATIC_PAGES = [
        ['path' => '/', 'title' => 'Home', 'description' => 'Overview of the marketplace, featured vending locations, and platform benefits.', 'changefreq' => 'daily', 'priority' => '1.0'],
        ['path' => '/machines', 'title' => 'Vending Machines', 'description' => 'The vending machine lineup available for placement, from snack and drink machines to smart coolers.', 'changefreq' => 'weekly', 'priority' => '0.9'],
        ['path' => '/products', 'title' => 'Products', 'description' => 'Snacks, beverages and other products stocked in our vending machines.', 'changefreq' => 'weekly', 'priority' => '0.7'],
        ['path' => '/micro-markets', 'title' => 'Micro Markets', 'description' => 'Self-checkout micro market kiosks for offices, apartment complexes and other high-traffic sites.', 'changefreq' => 'monthly', 'priority' => '0.8'],
        ['path' => '/office-coffee', 'title' => 'Office Coffee', 'description' => 'Office coffee service with machines, supplies and ongoing support.', 'changefreq' => 'monthly', 'priority' => '0.8'],
        ['path' => '/schools', 'title' => 'School Vending', 'description' => 'Vending programs designed for schools and campuses.', 'changefreq' => 'monthly', 'priority' => '0.7'],
        ['path' => '/industries', 'title' => 'Industries', 'description' => 'Commercial sectors we place vending machines in, such as offices, warehouses, apartments, hotels, gyms and medical sites.', 'changefreq' => 'weekly', 'priority' => '0.8'],
        ['path' => '/cities', 'title' => 'Cities We Serve', 'description' => 'Cities where vending locations are available.', 'changefreq' => 'weekly', 'priority' => '0.7'],
        ['path' => '/operators', 'title' => 'Operators', 'description' => 'Join the network as a vending operator and get access to verified location leads.', 'changefreq' => 'monthly', 'priority' => '0.8'],
        ['path' => '/resources', 'title' => 'Resources', 'description' => 'Guides and articles about vending machine placement and operations.', 'changefreq' => 'weekly', 'priority' => '0.7'],
        ['path' => '/about', 'title' => 'About Us', 'description' => 'Who we are and how the vending location marketplace works.', 'changefreq' => 'monthly', 'priority' => '0.6'],
        ['path' => '/contact', 'title' => 'Contact Us', 'description' => 'Get in touch with the team.', 'changefreq' => 'monthly', 'priority' => '0.6'],
    ];

    /**
     * Every public page: the static ones plus machines, industries, articles and legal pages from the database.
     *
     * @return list<array{path: string, title: string, description: string, lastmod: ?string, changefreq: string, priority: string}>
     */
    public function pages(): array
    {
        $dynamicPages = [
            ...$this->machinePages(),
            ...$this->industryPages(),
            ...$this->articlePages(),
            ...$this->legalPages(),
        ];

        $latestUpdate = collect($dynamicPages)->pluck('lastmod')->filter()->max();

        $staticPages = array_map(
            fn (array $page): array => $page + [
                'lastmod' => $page['path'] === '/' ? $latestUpdate : null,
            ],
            self::STATIC_PAGES,
        );

        return [...$staticPages, ...$dynamicPages];
    }

    /**
     * @return list<array{path: string, title: string, description: string, lastmod: ?string, changefreq: string, priority: string}>
     */
    private function machinePages(): array
    {
        return Machine::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['name', 'slug', 'description', 'updated_at'])
            ->map(fn (Machine $machine): array => $this->dynamicPage(
                '/machines/'.$machine->slug,
                $machine->name,
                $machine->description,
                $machine->updated_at,
                '0.7',
            ))
            ->all();
    }

    /**
     * @return list<array{path: string, title: string, description: string, lastmod: ?string, changefreq: string, priority: string}>
     */
    private function industryPages(): array
    {
        return LocationType::query()
            ->where('is_active', true)
            ->where('show_on_industries', true)
            ->whereNotNull('slug')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['name', 'slug', 'description', 'updated_at'])
            ->map(fn (LocationType $industry): array => $this->dynamicPage(
                '/industries/'.$industry->slug,
                $industry->name,
                $industry->description,
                $industry->updated_at,
                '0.7',
            ))
            ->all();
    }

    /**
     * @return list<array{path: string, title: string, description: string, lastmod: ?string, changefreq: string, priority: string}>
     */
    private function articlePages(): array
    {
        return Article::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['title', 'slug', 'excerpt', 'updated_at'])
            ->map(fn (Article $article): array => $this->dynamicPage(
                '/resources/'.$article->slug,
                $article->title,
                $article->excerpt,
                $article->updated_at,
                '0.6',
            ))
            ->all();
    }

    /**
     * @return list<array{path: string, title: string, description: string, lastmod: ?string, changefreq: string, priority: string}>
     */
    private function legalPages(): array
    {
        return LegalPage::query()
            ->whereIn('slug', ['privacy', 'terms', 'operator-terms'])
            ->orderBy('id')
            ->get(['title', 'slug', 'updated_at'])
            ->map(fn (LegalPage $legalPage): array => $this->dynamicPage(
                '/legal/'.$legalPage->slug,
                $legalPage->title,
                null,
                $legalPage->updated_at,
                '0.3',
                'yearly',
            ))
            ->all();
    }

    /**
     * @return array{path: string, title: string, description: string, lastmod: ?string, changefreq: string, priority: string}
     */
    private function dynamicPage(
        string $path,
        string $title,
        ?string $description,
        ?CarbonInterface $updatedAt,
        string $priority,
        string $changefreq = 'weekly',
    ): array {
        return [
            'path' => $path,
            'title' => $title,
            'description' => $description === null ? '' : trim(preg_replace('/\s+/', ' ', $description) ?? ''),
            'lastmod' => $updatedAt?->toDateString(),
            'changefreq' => $changefreq,
            'priority' => $priority,
        ];
    }
}
