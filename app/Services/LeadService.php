<?php

namespace App\Services;

use App\Enums\LeadIssueStatus;
use App\Models\CoffeeServiceRequest;
use App\Models\LeadPurchase;
use App\Models\OperatorProfile;
use App\Models\User;
use App\Models\VendingRequest;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Builds the operator lead marketplace from public vending and coffee requests.
 *
 * Contact details are only ever returned by {@see self::purchasedBy()}.
 */
class LeadService
{
    public const SOURCE_VENDING = 'vending';

    public const SOURCE_COFFEE = 'coffee';

    /**
     * Request statuses that make a lead available for purchase.
     *
     * @var list<string>
     */
    public const LISTED_STATUSES = ['new', 'reviewed'];

    /**
     * "Sold near you" covers leads sold within this many miles of the operator (the same reach as lead SMS alerts).
     */
    public const SOLD_RADIUS_MILES = 100;

    /**
     * How many of the most recent paid purchases are checked against the radius.
     */
    private const SOLD_SCAN_LIMIT = 500;

    /**
     * Leads the operator has not bought yet, without any contact details.
     *
     * @return list<array{
     *     id: string,
     *     source: string,
     *     type: string,
     *     cat: string,
     *     city: string,
     *     st: string,
     *     zip: string,
     *     dist: int|null,
     *     people: int|null,
     *     wants: string,
     *     io: string|null,
     *     price: int,
     *     listed: string
     * }>
     */
    public function availableFor(User $user, int $limit = 100): array
    {
        $profile = $user->operatorProfile;

        $leads = $this->listedModels()
            ->sortByDesc(fn (Model $lead): int => $lead->created_at?->getTimestamp() ?? 0)
            ->take($limit)
            ->map(fn (Model $lead): array => $this->mapAvailable($lead, $profile))
            ->values()
            ->all();

        return $leads;
    }

    /**
     * The operator's paid leads with full details, plus their pending
     * (payment still processing) leads with masked details only.
     *
     * @return list<array{
     *     id: string,
     *     purchase_id: int,
     *     can_report: bool,
     *     payment_status: string,
     *     purchased_at: string,
     *     name: string|null,
     *     type: string,
     *     city: string,
     *     st: string,
     *     zip: string,
     *     address: string|null,
     *     contact_name: string|null,
     *     contact_phone: string|null,
     *     email: string|null,
     *     price: int,
     *     credit_used: int|null,
     *     status: string,
     *     receipt_url: string|null,
     *     card_brand: string|null,
     *     card_last4: string|null
     * }>
     */
    public function purchasedBy(User $user): array
    {
        $windowDays = app(LeadIssueSettingsService::class)->reportWindowDays();

        return $user->leadPurchases()
            ->whereIn('status', [LeadPurchase::STATUS_PAID, LeadPurchase::STATUS_PENDING])
            ->with(['leadable', 'issueReport'])
            ->latest()
            ->get()
            ->filter(fn (LeadPurchase $purchase): bool => $purchase->leadable !== null)
            ->map(fn (LeadPurchase $purchase): array => $this->mapPurchased($purchase, $windowDays))
            ->values()
            ->all();
    }

    /**
     * Leads that were sold (payment confirmed) within {@see self::SOLD_RADIUS_MILES} of the operator, newest first.
     *
     * Only public, non-identifying details are returned: no business name, address, contact details or buyer.
     * Without a saved location there is nothing to measure from, so the list is empty.
     *
     * @return list<array{
     *     id: string,
     *     type: string,
     *     city: string,
     *     st: string,
     *     zip: string,
     *     dist: int,
     *     people: int|null,
     *     wants: string,
     *     price: int,
     *     sold: string,
     *     mine: bool
     * }>
     */
    public function soldNear(User $user, int $limit = 50): array
    {
        $profile = $user->operatorProfile;

        if ($profile === null || $profile->latitude === null || $profile->longitude === null) {
            return [];
        }

        $sold = [];

        $purchases = LeadPurchase::query()
            ->paid()
            ->with('leadable')
            ->latest('purchased_at')
            ->latest('id')
            ->limit(self::SOLD_SCAN_LIMIT)
            ->get();

        foreach ($purchases as $purchase) {
            $lead = $purchase->leadable;

            if ($lead === null) {
                continue;
            }

            $distance = $this->distanceInMiles($profile, $lead);

            if ($distance === null || $distance > self::SOLD_RADIUS_MILES) {
                continue;
            }

            $sold[] = $this->mapSold($purchase, $lead, $distance, $user);

            if (count($sold) >= $limit) {
                break;
            }
        }

        return $sold;
    }

    /**
     * @return array{available: int, purchased: int}
     */
    public function counts(User $user): array
    {
        return [
            'available' => $this->listedModels()->count(),
            'purchased' => $user->leadPurchases()->paid()->count(),
        ];
    }

    /**
     * Listed leads that nobody holds: leads are exclusive, so a lead with a
     * pending or paid purchase (from any operator) is no longer available.
     *
     * @return Collection<int, Model>
     */
    private function listedModels(): Collection
    {
        $leads = collect();

        foreach ([self::SOURCE_VENDING, self::SOURCE_COFFEE] as $source) {
            $heldIds = LeadPurchase::query()
                ->holdingLead()
                ->where('leadable_type', $source)
                ->pluck('leadable_id');

            $leads = $leads->concat(
                $this->modelClass($source)::query()
                    ->whereIn('status', self::LISTED_STATUSES)
                    ->whereNotNull('price')
                    ->where('price', '>=', 1)
                    ->whereNotIn('id', $heldIds)
                    ->latest()
                    ->limit(200)
                    ->get(),
            );
        }

        return $leads;
    }

    /**
     * @return class-string<Model>
     */
    public function modelClass(string $source): string
    {
        /** @var class-string<Model> $class */
        $class = Relation::getMorphedModel($source);

        return $class;
    }

    /**
     * The public lead id shown to operators, e.g. "V-12" or "C-7".
     */
    public static function publicIdFor(string $source, int $id): string
    {
        return ($source === self::SOURCE_COFFEE ? 'C-' : 'V-').$id;
    }

    private function sourceOf(Model $lead): string
    {
        return $lead instanceof CoffeeServiceRequest
            ? self::SOURCE_COFFEE
            : self::SOURCE_VENDING;
    }

    private function publicId(Model $lead): string
    {
        return self::publicIdFor($this->sourceOf($lead), (int) $lead->getKey());
    }

    /**
     * @return array<string, mixed>
     */
    private function mapAvailable(Model $lead, ?OperatorProfile $profile): array
    {
        $isCoffee = $lead instanceof CoffeeServiceRequest;
        $postalCode = (string) ($lead->postal_code ?: ($isCoffee ? $lead->zip_code : ''));

        return [
            'id' => $this->publicId($lead),
            'source' => $this->sourceOf($lead),
            'type' => $isCoffee ? 'Office coffee' : $lead->locationTypeLabel(),
            'cat' => $isCoffee ? 'Coffee' : $lead->location_type,
            'city' => (string) $lead->city,
            'st' => (string) $lead->state,
            'zip' => Str::substr($postalCode, 0, 3),
            'dist' => $this->distanceInMiles($profile, $lead),
            'people' => $isCoffee ? null : $lead->employees_on_site,
            'wants' => $isCoffee ? 'Office coffee' : implode(', ', $lead->machine_interests ?? []),
            'io' => $isCoffee ? null : $lead->placement,
            'price' => (int) $lead->price,
            'listed' => $this->listedLabel($lead),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPurchased(LeadPurchase $purchase, ?int $windowDays): array
    {
        /** @var VendingRequest|CoffeeServiceRequest $lead */
        $lead = $purchase->leadable;
        $isCoffee = $lead instanceof CoffeeServiceRequest;
        $isPaid = $purchase->isPaid();
        $postalCode = (string) ($lead->postal_code ?: ($isCoffee ? $lead->zip_code : ''));

        // Contact details are only serialised once Square confirmed the payment.
        return [
            'id' => $this->publicId($lead),
            'purchase_id' => $purchase->id,
            'can_report' => app(LeadIssueReportService::class)->canReportWithin($purchase, $purchase->issueReport !== null, $windowDays),
            'payment_status' => $isPaid ? 'paid' : 'pending',
            'purchased_at' => ($purchase->purchased_at ?? $purchase->created_at)->format('M j'),
            'name' => $isPaid ? $lead->business_name : null,
            'type' => $isCoffee ? 'Office coffee' : $lead->locationTypeLabel(),
            'city' => (string) $lead->city,
            'st' => (string) $lead->state,
            'zip' => $isPaid ? $postalCode : Str::substr($postalCode, 0, 3),
            'address' => $isPaid
                ? (string) ($lead->address ?: ($isCoffee ? $lead->zip_code : $lead->address_or_zip))
                : null,
            'contact_name' => $isPaid ? $lead->contact_name : null,
            'contact_phone' => $isPaid ? $lead->phone : null,
            'email' => $isPaid ? $lead->email : null,
            'price' => $purchase->price,
            'credit_used' => null,
            'status' => match ($purchase->issueReport?->status) {
                LeadIssueStatus::OPEN => 'issue_open',
                LeadIssueStatus::APPROVED => 'issue_approved',
                LeadIssueStatus::REJECTED => 'issue_rejected',
                default => 'ok',
            },
            'receipt_url' => $isPaid ? $purchase->square_receipt_url : null,
            'card_brand' => $isPaid ? $purchase->card_brand : null,
            'card_last4' => $isPaid ? $purchase->card_last4 : null,
        ];
    }

    /**
     * @return array{id: string, type: string, city: string, st: string, zip: string, dist: int, people: int|null, wants: string, price: int, sold: string, mine: bool}
     */
    private function mapSold(LeadPurchase $purchase, Model $lead, int $distance, User $viewer): array
    {
        $isCoffee = $lead instanceof CoffeeServiceRequest;
        $postalCode = (string) ($lead->postal_code ?: ($isCoffee ? $lead->zip_code : ''));

        return [
            'id' => $this->publicId($lead),
            'type' => $isCoffee ? 'Office coffee' : $lead->locationTypeLabel(),
            'city' => (string) $lead->city,
            'st' => (string) $lead->state,
            'zip' => Str::substr($postalCode, 0, 3),
            'dist' => $distance,
            'people' => $isCoffee ? null : $lead->employees_on_site,
            'wants' => $isCoffee ? 'Office coffee' : implode(', ', $lead->machine_interests ?? []),
            'price' => (int) $purchase->price,
            'sold' => $this->dateLabel($purchase->purchased_at ?? $purchase->paid_at ?? $purchase->created_at),
            'mine' => $purchase->user_id === $viewer->id,
        ];
    }

    private function listedLabel(Model $lead): string
    {
        return $this->dateLabel($lead->created_at);
    }

    private function dateLabel(?CarbonInterface $date): string
    {
        return match (true) {
            $date === null => '',
            $date->isToday() => 'Today',
            $date->isYesterday() => 'Yesterday',
            default => $date->format('M j'),
        };
    }

    private function distanceInMiles(?OperatorProfile $profile, Model $lead): ?int
    {
        if ($profile === null
            || $profile->latitude === null || $profile->longitude === null
            || $lead->latitude === null || $lead->longitude === null) {
            return null;
        }

        $fromLat = deg2rad((float) $profile->latitude);
        $toLat = deg2rad((float) $lead->latitude);
        $deltaLat = $toLat - $fromLat;
        $deltaLng = deg2rad((float) $lead->longitude - (float) $profile->longitude);

        $a = sin($deltaLat / 2) ** 2
            + cos($fromLat) * cos($toLat) * sin($deltaLng / 2) ** 2;

        return (int) round(3958.8 * 2 * asin(min(1, sqrt($a))));
    }
}
