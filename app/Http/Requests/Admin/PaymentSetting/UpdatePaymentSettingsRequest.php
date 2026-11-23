<?php

namespace App\Http\Requests\Admin\PaymentSetting;

use App\Services\Payments\SquareSettingsService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentSettingsRequest extends FormRequest
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
            'environment' => ['required', 'string', Rule::in(SquareSettingsService::ENVIRONMENTS)],
            'application_id' => ['nullable', 'string', 'max:255'],
            'location_id' => ['nullable', 'string', 'max:255'],
            'webhook_url' => ['nullable', 'string', 'url', 'max:255'],
            'access_token' => ['nullable', 'string', 'max:1000'],
            'webhook_signature_key' => ['nullable', 'string', 'max:1000'],
            'clear_access_token' => ['sometimes', 'boolean'],
            'clear_webhook_signature_key' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'application_id' => 'application ID',
            'location_id' => 'location ID',
            'webhook_url' => 'webhook URL',
            'access_token' => 'access token',
            'webhook_signature_key' => 'webhook signature key',
        ];
    }
}
