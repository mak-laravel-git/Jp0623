<?php

namespace App\Http\Requests\Operator;

use App\Concerns\ProfileValidationRules;
use App\Services\OperatorAccountService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SendAccountPhoneCodeRequest extends FormRequest
{
    use ProfileValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->operatorProfile !== null
            && app(OperatorAccountService::class)->usesSmsVerification();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'phone' => $this->textablePhoneRules(),
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
