<?php

namespace App\Mail\Concerns;

use App\Models\CoffeeServiceRequest;
use App\Models\LeadPurchase;
use App\Services\LeadService;

/**
 * Shared view data for the two emails sent when an operator buys a lead.
 *
 * Expects the using mailable to expose a public `$purchase` (a paid
 * {@see LeadPurchase}).
 */
trait DescribesLeadPurchase
{
    /**
     * The operator who bought the lead.
     *
     * @return array{company: string, name: string, email: string, phone: string|null}
     */
    protected function buyerDetails(): array
    {
        $user = $this->purchase->user;
        $profile = $user->operatorProfile;
        $fullName = trim(($profile?->first_name ?? '').' '.($profile?->last_name ?? ''));

        return [
            'company' => $profile?->company_name ?: $user->name,
            'name' => $fullName !== '' ? $fullName : $user->name,
            'email' => $user->email,
            'phone' => $profile?->phone ?: null,
        ];
    }

    /**
     * The location that asked for vending or coffee service.
     *
     * @return array{id: string, type: string, business: string, contact_name: string, email: string, phone: string, address: string, location: string}
     */
    protected function leadDetails(): array
    {
        $lead = $this->purchase->leadable;
        $isCoffee = $lead instanceof CoffeeServiceRequest;

        return [
            'id' => LeadService::publicIdFor($this->purchase->leadable_type, $this->purchase->leadable_id),
            'type' => $isCoffee ? 'Office coffee' : $lead->locationTypeLabel(),
            'business' => (string) $lead->business_name,
            'contact_name' => (string) $lead->contact_name,
            'email' => (string) $lead->email,
            'phone' => (string) $lead->phone,
            'address' => (string) ($lead->address ?: ($isCoffee ? $lead->zip_code : $lead->address_or_zip)),
            'location' => collect([$lead->city, $lead->state])->filter()->implode(', '),
        ];
    }
}
