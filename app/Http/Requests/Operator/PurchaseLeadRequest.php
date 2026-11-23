<?php

namespace App\Http\Requests\Operator;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Client input is the single-use card token from Square's Web Payments SDK (not needed when Credits
     * cover the whole price) and whether to use Credits; the price is always decided on the server.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'use_credits' => ['sometimes', 'boolean'],
            'source_id' => [Rule::requiredIf(! $this->boolean('use_credits')), 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'source_id' => 'card',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source_id.required' => 'Enter your card details to complete the purchase.',
        ];
    }
}
