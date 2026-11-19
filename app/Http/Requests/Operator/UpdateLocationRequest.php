<?php

namespace App\Http\Requests\Operator;

use App\Services\OperatorLocationService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->operatorProfile !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'city' => ['required', 'string', 'max:255', $this->withinPrimaryCityChangeLimit()],
            'state' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'additional_service_areas' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'city' => 'primary city',
            'latitude' => 'primary city',
            'longitude' => 'primary city',
            'additional_service_areas' => 'additional service areas',
        ];
    }

    /**
     * Reject a different primary city once the operator has used all their changes.
     */
    private function withinPrimaryCityChangeLimit(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $profile = $this->user()->operatorProfile;
            $service = app(OperatorLocationService::class);

            if ($service->changesPrimaryCity($profile, (string) $value, (string) $this->input('state'))
                && $service->changesRemaining($profile) < 1) {
                $fail(sprintf(
                    'You have used all %d primary city changes allowed in %d days.',
                    OperatorLocationService::MAX_PRIMARY_CITY_CHANGES,
                    OperatorLocationService::CHANGE_WINDOW_DAYS,
                ));
            }
        };
    }
}
