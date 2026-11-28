<?php

namespace App\Concerns;

use App\Models\User;
use App\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate operator phone numbers.
     *
     * @return list<string|Closure>
     */
    protected function phoneRules(): array
    {
        return [
            'required',
            'string',
            'max:50',
            'regex:/^[0-9+\-() ]+$/',
            function (string $attribute, mixed $value, Closure $fail): void {
                $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
                $length = strlen($digits);

                if ($length < 7 || $length > 15) {
                    $fail('The phone must contain between 7 and 15 digits.');
                }
            },
        ];
    }

    /**
     * Phone rules for a number that a text message has to reach.
     *
     * @return list<string|Closure>
     */
    protected function textablePhoneRules(): array
    {
        return [
            'bail',
            ...$this->phoneRules(),
            function (string $attribute, mixed $value, Closure $fail): void {
                if (PhoneNumber::toE164((string) $value) === null) {
                    $fail('Enter the full phone number, including the area code.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function phoneMessages(): array
    {
        return [
            'phone.regex' => 'The phone may only contain digits and phone punctuation.',
        ];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }
}
