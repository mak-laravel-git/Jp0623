<?php

namespace Database\Factories;

use App\Enums\LeadRefundAction;
use App\Enums\LeadRefundStatus;
use App\Enums\LeadRefundType;
use App\Models\LeadIssueReport;
use App\Models\LeadRefund;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LeadRefund>
 */
class LeadRefundFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_issue_report_id' => LeadIssueReport::factory()->approved(),
            'lead_purchase_id' => fn (array $attributes): int => LeadIssueReport::query()->findOrFail($attributes['lead_issue_report_id'])->lead_purchase_id,
            'user_id' => fn (array $attributes): int => LeadIssueReport::query()->findOrFail($attributes['lead_issue_report_id'])->user_id,
            'type' => LeadRefundType::CREDIT,
            'total_amount' => 50,
            'credit_amount' => 50,
            'card_amount' => 0,
            'status' => LeadRefundStatus::COMPLETED,
            'idempotency_key' => (string) Str::uuid(),
            'lead_action' => LeadRefundAction::RESELL,
            'admin_note' => 'Refunded.',
        ];
    }
}
