<?php

namespace Database\Seeders;

use App\Models\MicroMarketFaq;
use Illuminate\Database\Seeder;

class MicroMarketFaqSeeder extends Seeder
{
    /**
     * Seed the starter micro markets page FAQ items. Questions that already
     * exist are left alone, so edits made by an admin are never overwritten.
     */
    public function run(): void
    {
        foreach ($this->items() as $index => $item) {
            MicroMarketFaq::query()->firstOrCreate(
                ['question' => $item['question']],
                [
                    'answer' => $item['answer'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @return list<array{question: string, answer: string}>
     */
    private function items(): array
    {
        return [
            [
                'question' => 'How much space does a micro market need?',
                'answer' => 'Most start at about 8 by 10 feet. We size it to your room.',
            ],
            [
                'question' => 'Is there a cost to the business?',
                'answer' => 'No. Equipment, install and stocking are covered by the operator.',
            ],
            [
                'question' => 'What about theft?',
                'answer' => 'Kiosks have cameras and most markets have very low loss.',
            ],
            [
                'question' => 'Do you offer micro markets nationwide?',
                'answer' => 'Yes, through local operators in all 50 states.',
            ],
        ];
    }
}
