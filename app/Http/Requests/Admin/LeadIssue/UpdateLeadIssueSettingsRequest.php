<?php

namespace App\Http\Requests\Admin\LeadIssue;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLeadIssueSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * An empty value means no time limit.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'report_window_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'report_window_days' => 'number of days',
        ];
    }
}
