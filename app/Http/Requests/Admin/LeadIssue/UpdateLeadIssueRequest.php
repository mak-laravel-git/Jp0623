<?php

namespace App\Http\Requests\Admin\LeadIssue;

use App\Enums\LeadIssueStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeadIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in([LeadIssueStatus::APPROVED->value, LeadIssueStatus::REJECTED->value])],
            'admin_note' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'status' => 'decision',
            'admin_note' => 'note',
        ];
    }
}
