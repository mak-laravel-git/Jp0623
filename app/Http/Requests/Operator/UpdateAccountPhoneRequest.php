<?php

namespace App\Http\Requests\Operator;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Services\OperatorAccountService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountPhoneRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->operatorProfile !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if (app(OperatorAccountService::class)->usesSmsVerification()) {
            return [
                'phone' => $this->textablePhoneRules(),
                'code' => ['required', 'digits:6'],
            ];
        }

        return [
            'phone' => $this->phoneRules(),
            'current_password' => $this->currentPasswordRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->phoneMessages();
    }
}
