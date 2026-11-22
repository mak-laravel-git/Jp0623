<?php

namespace App\Http\Requests\Admin\MembershipSetting;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMembershipCustomPriceRequest extends FormRequest
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
            'monthly_price' => ['required', 'integer', 'min:1', 'max:999999'],
            'send_email' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'monthly_price' => 'monthly price',
            'send_email' => 'email notification',
        ];
    }
}
