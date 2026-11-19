<?php

namespace Database\Seeders;

use App\Models\RouteListing;
use Illuminate\Database\Seeder;

class RouteListingSeeder extends Seeder
{
    /**
     * Seed demo "Established locations & routes for sale" listings.
     */
    public function run(): void
    {
        foreach ($this->items() as $item) {
            RouteListing::query()->updateOrCreate(
                ['city' => $item['city'], 'about' => $item['about']],
                $item + ['is_active' => true],
            );
        }
    }

    /**
     * @return list<array{city: string, about: string, locations_count: int, machines_count: int, annual_revenue: int, asking_price: int, seller_financing: bool, listed_at: string}>
     */
    private function items(): array
    {
        return [
            [
                'city' => 'Dallas–Fort Worth, TX',
                'about' => 'Office & light industrial',
                'locations_count' => 14,
                'machines_count' => 22,
                'annual_revenue' => 168000,
                'asking_price' => 210000,
                'seller_financing' => true,
                'listed_at' => '2026-10-25',
            ],
            [
                'city' => 'Charlotte, NC',
                'about' => 'Healthcare combo route',
                'locations_count' => 9,
                'machines_count' => 13,
                'annual_revenue' => 96500,
                'asking_price' => 118000,
                'seller_financing' => false,
                'listed_at' => '2026-10-19',
            ],
            [
                'city' => 'Orlando, FL',
                'about' => 'Apartment AI coolers',
                'locations_count' => 6,
                'machines_count' => 6,
                'annual_revenue' => 54000,
                'asking_price' => 72000,
                'seller_financing' => true,
                'listed_at' => '2026-10-11',
            ],
            [
                'city' => 'Indianapolis, IN',
                'about' => 'School & gym snack route',
                'locations_count' => 11,
                'machines_count' => 17,
                'annual_revenue' => 81200,
                'asking_price' => 95000,
                'seller_financing' => true,
                'listed_at' => '2026-10-03',
            ],
            [
                'city' => 'Phoenix, AZ',
                'about' => 'Warehouse drink route',
                'locations_count' => 8,
                'machines_count' => 12,
                'annual_revenue' => 72500,
                'asking_price' => 84000,
                'seller_financing' => false,
                'listed_at' => '2026-09-21',
            ],
        ];
    }
}
