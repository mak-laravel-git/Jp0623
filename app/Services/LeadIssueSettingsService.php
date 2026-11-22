<?php

namespace App\Services;

use App\Models\LeadIssueSetting;

/**
 * Admin-controlled settings for returning purchased leads.
 */
class LeadIssueSettingsService
{
    /**
     * Days after buying a lead that the operator can still send it back; null means no time limit.
     */
    public function reportWindowDays(): ?int
    {
        $days = LeadIssueSetting::query()->value('report_window_days');

        return is_numeric($days) && (int) $days > 0 ? (int) $days : null;
    }

    public function updateReportWindowDays(?int $days): LeadIssueSetting
    {
        $setting = LeadIssueSetting::query()->first() ?? new LeadIssueSetting;
        $setting->fill(['report_window_days' => $days])->save();

        return $setting;
    }
}
