<?php

namespace App\Services;

use Illuminate\Http\Request;

/**
 * Resolves the title, description, canonical URL and JSON-LD schema for public pages.
 */
class SeoMetaService
{
    /**
     * Route names of the public pages that carry SEO metadata.
     *
     * @var list<string>
     */
    private const PUBLIC_ROUTES = [
        'home',
        'machines',
        'machines.show',
        'products',
        'micro-markets',
        'office-coffee',
        'contact',
        'about',
        'schools',
        'resources',
        'resources.show',
        'cities',
        'operators',
        'legal.show',
        'industries',
        'industries.show',
    ];

    /**
     * SEO data for the current request, or null when it is not a public page.
     *
     * @return array{title: ?string, description: ?string, canonical: string, schema: ?array<string, mixed>}|null
     */
    public function forRequest(Request $request): ?array
    {
        if (! $request->isMethod('GET') || ! in_array($request->route()?->getName(), self::PUBLIC_ROUTES, true)) {
            return null;
        }

        $path = '/'.ltrim($request->path(), '/');
        $page = config('seo.pages.'.$path);

        return [
            'title' => $page['title'] ?? null,
            'description' => $page['description'] ?? null,
            'canonical' => $this->absoluteUrl($path),
            'schema' => $this->schemaFor($path),
        ];
    }

    public function absoluteUrl(string $path): string
    {
        return rtrim((string) config('app.url'), '/').$path;
    }

    /**
     * JSON-LD graph for the pages that have structured data.
     *
     * @return array<string, mixed>|null
     */
    private function schemaFor(string $path): ?array
    {
        return match ($path) {
            '/' => $this->homeSchema(),
            '/micro-markets' => $this->microMarketsSchema(),
            '/resources/ai-vs-market' => $this->aiVsMarketSchema(),
            default => null,
        };
    }

    /**
     * Organization and WebSite nodes that the other pages reference by @id.
     *
     * @return array<string, mixed>
     */
    private function homeSchema(): array
    {
        $home = $this->absoluteUrl('/');

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => $home.'#organization',
                    'name' => config('seo.site_name'),
                    'url' => $home,
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => $home.'#website',
                    'url' => $home,
                    'name' => config('seo.site_name'),
                    'publisher' => ['@id' => $home.'#organization'],
                    'inLanguage' => 'en-US',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function microMarketsSchema(): array
    {
        $home = $this->absoluteUrl('/');
        $url = $this->absoluteUrl('/micro-markets');

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'WebPage',
                    '@id' => $url.'#webpage',
                    'url' => $url,
                    'name' => 'Micro Market Services',
                    'description' => 'Managed self-service micro market solutions for workplaces, including food, snacks, beverages and self-checkout.',
                    'isPartOf' => ['@id' => $home.'#website'],
                    'mainEntity' => ['@id' => $url.'#service'],
                    'breadcrumb' => ['@id' => $url.'#breadcrumb'],
                    'inLanguage' => 'en-US',
                ],
                [
                    '@type' => 'Service',
                    '@id' => $url.'#service',
                    'name' => 'Micro Market Services',
                    'serviceType' => 'Workplace micro market management',
                    'url' => $url,
                    'description' => 'Managed self-checkout markets for workplaces and commercial properties, providing convenient access to fresh food, snacks and beverages.',
                    'provider' => ['@id' => $home.'#organization'],
                    'areaServed' => [
                        '@type' => 'Country',
                        'name' => 'United States',
                    ],
                ],
                $this->breadcrumb($url, [
                    ['Home', $home],
                    ['Micro Markets', $url],
                ]),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function aiVsMarketSchema(): array
    {
        $home = $this->absoluteUrl('/');
        $url = $this->absoluteUrl('/resources/ai-vs-market');

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'BlogPosting',
                    '@id' => $url.'#article',
                    'headline' => 'Sandstar AI Coolers vs. Micro Markets: Which One Fits?',
                    'description' => 'Compare Sandstar AI coolers and workplace micro markets based on available space, employee headcount and food service requirements.',
                    'url' => $url,
                    'mainEntityOfPage' => [
                        '@type' => 'WebPage',
                        '@id' => $url.'#webpage',
                    ],
                    'author' => ['@id' => $home.'#organization'],
                    'publisher' => ['@id' => $home.'#organization'],
                    'inLanguage' => 'en-US',
                ],
                $this->breadcrumb($url, [
                    ['Home', $home],
                    ['Resources', $this->absoluteUrl('/resources')],
                    ['Sandstar AI Coolers vs. Micro Markets', $url],
                ]),
            ],
        ];
    }

    /**
     * @param  list<array{0: string, 1: string}>  $trail  name and URL pairs, in order
     * @return array<string, mixed>
     */
    private function breadcrumb(string $pageUrl, array $trail): array
    {
        return [
            '@type' => 'BreadcrumbList',
            '@id' => $pageUrl.'#breadcrumb',
            'itemListElement' => array_map(
                fn (array $crumb, int $index): array => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $crumb[0],
                    'item' => $crumb[1],
                ],
                $trail,
                array_keys($trail),
            ),
        ];
    }
}
