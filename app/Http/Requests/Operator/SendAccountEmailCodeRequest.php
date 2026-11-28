<?php

namespace App\Http\Requests\Operator;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class SendAccountEmailCodeRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => [
                ...$this->emailRules($this->user()->id),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (Str::lower((string) $value) === Str::lower($this->user()->email)) {
                        $fail('That is already your email address.');
                    }
                },
            ],
            'current_password' => $this->currentPasswordRules(),
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }
}
