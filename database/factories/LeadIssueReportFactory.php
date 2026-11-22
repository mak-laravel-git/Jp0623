<?php

namespace Database\Factories;

use App\Enums\LeadIssueCategory;
use App\Enums\LeadIssueStatus;
use App\Models\LeadIssueReport;
use App\Models\LeadPurchase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadIssueReport>
 */
class LeadIssueReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_purchase_id' => LeadPurchase::factory(),
            'user_id' => fn (array $attributes): int => LeadPurchase::query()->findOrFail($attributes['lead_purchase_id'])->user_id,
            'category' => LeadIssueCategory::FAKE_LEAD,
            'reason' => fake()->sentence(12),
            'status' => LeadIssueStatus::OPEN,
            'lead_reference' => 'V-'.fake()->numberBetween(1, 999),
            'lead_name' => fake()->company(),
            'lead_type' => 'Office',
            'lead_city' => fake()->city(),
            'price' => (int) config('leads.price'),
            'purchased_at' => now(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => LeadIssueStatus::APPROVED,
            'admin_note' => 'Checked and approved.',
            'resolved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => LeadIssueStatus::REJECTED,
            'admin_note' => 'The lead details were correct.',
            'resolved_at' => now(),
        ]);
    }
}
