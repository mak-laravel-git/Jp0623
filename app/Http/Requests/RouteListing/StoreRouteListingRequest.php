<?php

namespace App\Http\Requests\RouteListing;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRouteListingRequest extends FormRequest
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
            'city' => ['required', 'string', 'max:255'],
            'about' => ['required', 'string', 'max:255'],
            'locations_count' => ['required', 'integer', 'min:0', 'max:100000'],
            'machines_count' => ['required', 'integer', 'min:0', 'max:100000'],
            'annual_revenue' => ['required', 'integer', 'min:0', 'max:2000000000'],
            'asking_price' => ['required', 'integer', 'min:0', 'max:2000000000'],
            'seller_financing' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'listed_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'city' => 'City',
            'about' => 'About',
            'locations_count' => 'Locations',
            'machines_count' => 'Machines',
            'annual_revenue' => 'Annual revenue',
            'asking_price' => 'Asking price',
            'seller_financing' => 'Seller financing',
            'is_active' => 'Active',
            'listed_at' => 'Listed date',
        ];
    }
}
