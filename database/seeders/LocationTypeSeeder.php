<?php

namespace Database\Seeders;

use App\Models\LocationType;
use Illuminate\Database\Seeder;

class LocationTypeSeeder extends Seeder
{
    /**
     * Seed the default location types. They drive the request form "Type of location"
     * dropdown AND the industries shown on the home, /industries and machines pages.
     */
    public function run(): void
    {
        foreach ($this->items() as $index => $item) {
            LocationType::query()->updateOrCreate(
                ['name' => $item['name']],
                [
                    'slug' => $item['slug'],
                    'description' => $item['description'],
                    'image' => $item['image'],
                    'kind' => $item['kind'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                    // "Other" stays in the request form only; it is not a public industry.
                    'show_on_industries' => $item['name'] !== 'Other',
                ],
            );
        }
    }

    /**
     * @return list<array{name: string, slug: string, description: string|null, kind: string|null, image: string|null}>
     */
    private function items(): array
    {
        return [
            [
                'name' => 'Residential/Hotels',
                'slug' => 'residential-hotels',
                'description' => 'Lobby, clubhouse and guest-floor vending that residents and guests can use any time.',
                'kind' => 'cooler',
                'image' => '/images/machines/ai-cooler.png',
            ],
            [
                'name' => 'Industrial Facilities',
                'slug' => 'industrial-facilities',
                'description' => 'Durable, high-capacity machines for large crews and long shifts, with around-the-clock vending for every shift.',
                'kind' => 'snack',
                'image' => '/images/machines/tradational-vending.png',
            ],
            [
                'name' => 'Office',
                'slug' => 'office',
                'description' => 'Quiet machines, healthy options and coffee for daytime teams.',
                'kind' => 'combo',
                'image' => '/images/machines/Alpine-ST5000.png',
            ],
            [
                'name' => 'Warehouse',
                'slug' => 'warehouse',
                'description' => 'Durable, high-capacity machines for large crews and long shifts, with around-the-clock vending for every shift.',
                'kind' => 'snack',
                'image' => '/images/machines/tradational-vending.png',
            ],
            [
                'name' => 'Recreational Facilities',
                'slug' => 'recreational-facilities',
                'description' => 'Protein, electrolytes and recovery drinks for members and visitors.',
                'kind' => 'healthy',
                'image' => '/images/machines/healthy-vending.png',
            ],
            [
                'name' => 'Medical Facility',
                'slug' => 'medical-facility',
                'description' => '24/7 food and drinks for staff, patients and visitors.',
                'kind' => 'food',
                'image' => '/images/machines/coffee1.jpg',
            ],
            [
                'name' => 'School',
                'slug' => 'school',
                'description' => 'Machines and products that follow school nutrition rules, with card and phone pay for campuses.',
                'kind' => 'healthy',
                'image' => '/images/machines/healthy-vending.png',
            ],
            [
                'name' => 'Other',
                'slug' => 'other',
                'description' => null,
                'kind' => null,
                'image' => null,
            ],
        ];
    }
}
