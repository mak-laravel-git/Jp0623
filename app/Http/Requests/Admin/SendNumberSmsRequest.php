<?php

namespace App\Http\Requests\Admin;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class SendNumberSmsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone_number' => ['required', 'string', 'regex:/^\+[1-9]\d{7,14}$/'],
            'message' => ['required', 'string', 'max:1600'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone_number.required' => 'Enter a phone number.',
            'phone_number.regex' => 'Enter a valid phone number with its country code, for example +18325550100.',
            'message.required' => 'Message is required.',
        ];
    }

    /**
     * Accept a number typed like "(832) 555-0100". Anything containing other characters is left to fail validation.
     */
    protected function prepareForValidation(): void
    {
        $number = $this->input('phone_number');

        if (is_string($number) && preg_match('/^\+?[\d\s().-]+$/', $number) === 1) {
            $this->merge(['phone_number' => PhoneNumber::toE164($number) ?? $number]);
        }
    }
}
