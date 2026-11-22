<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\SeoPageService;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function __construct(private SeoPageService $pages) {}

    public function sitemap(): Response
    {
        $entries = array_map(function (array $page): string {
            $lines = ['  <url>', '    <loc>'.e($this->absoluteUrl($page['path'])).'</loc>'];

            if ($page['lastmod'] !== null) {
                $lines[] = '    <lastmod>'.$page['lastmod'].'</lastmod>';
            }

            $lines[] = '    <changefreq>'.$page['changefreq'].'</changefreq>';
            $lines[] = '    <priority>'.$page['priority'].'</priority>';
            $lines[] = '  </url>';

            return implode("\n", $lines);
        }, $this->pages->pages());

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .implode("\n", $entries)."\n"
            .'</urlset>'."\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function llms(): Response
    {
        $links = array_map(function (array $page): string {
            $line = '- ['.$page['title'].']('.$this->absoluteUrl($page['path']).')';

            return $page['description'] === '' ? $line : $line.': '.$page['description'];
        }, $this->pages->pages());

        $text = "# Vending Machine Locations For Sale\n\n"
            ."> A dedicated online marketplace connecting vending machine operators with verified, high-traffic commercial locations ready for machine placement, as well as enabling locators and sellers to list available vending spots.\n\n"
            ."## About the Platform\n\n"
            ."Vending Machine Locations For Sale streamlines the process of expanding a vending business by offering vetted location leads across various commercial sectors, including apartment complexes, corporate offices, gyms, warehouses, hotels, and schools.\n\n"
            ."## Main Navigation & Core Pages\n\n"
            .implode("\n", $links)."\n";

        return response($text, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function absoluteUrl(string $path): string
    {
        return rtrim((string) config('app.url'), '/').$path;
    }
}
