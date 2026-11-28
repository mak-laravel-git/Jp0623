<?php

namespace App\Http\Requests\Admin\TwilioSetting;

use App\Support\PhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTwilioSettingsRequest extends FormRequest
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
            'enabled' => ['required', 'boolean'],
            'sid' => ['nullable', 'string', 'regex:/^AC[0-9a-fA-F]{32}$/'],
            'auth_token' => ['nullable', 'string', 'max:255'],
            'from_number' => ['nullable', 'string', 'regex:/^\+[1-9]\d{7,14}$/'],
            'messaging_service_sid' => ['nullable', 'string', 'regex:/^MG[0-9a-fA-F]{32}$/'],
            'lead_radius_miles' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'clear_auth_token' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sid.regex' => 'The Account SID starts with AC and is 34 characters long.',
            'messaging_service_sid.regex' => 'The Messaging Service SID starts with MG and is 34 characters long.',
            'from_number.regex' => 'Enter the sender number with its country code, for example +18325550100.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'sid' => 'Account SID',
            'auth_token' => 'auth token',
            'from_number' => 'sender number',
            'messaging_service_sid' => 'Messaging Service SID',
            'lead_radius_miles' => 'lead radius',
        ];
    }

    /**
     * Accept a sender number typed like "(832) 555-0100".
     */
    protected function prepareForValidation(): void
    {
        $number = $this->input('from_number');

        if (is_string($number) && $number !== '') {
            $this->merge(['from_number' => PhoneNumber::toE164($number) ?? $number]);
        }
    }
}
