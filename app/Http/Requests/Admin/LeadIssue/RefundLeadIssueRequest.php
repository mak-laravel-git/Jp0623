<?php

namespace App\Http\Requests\Admin\LeadIssue;

use App\Enums\LeadRefundAction;
use App\Enums\LeadRefundType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RefundLeadIssueRequest extends FormRequest
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
            'type' => ['required', 'string', Rule::enum(LeadRefundType::class)],
            'lead_action' => ['required', 'string', Rule::enum(LeadRefundAction::class)],
            'admin_note' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => 'refund type',
            'lead_action' => 'lead option',
            'admin_note' => 'note',
        ];
    }
}
