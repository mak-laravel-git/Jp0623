<?php

namespace App\Services;

use App\Enums\RouteInquiryStatus;
use App\Mail\RouteInquiryReceivedMail;
use App\Models\RouteListing;
use App\Models\RouteListingInquiry;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class RouteListingInquiryService
{
    /**
     * Seconds during which an identical inquiry (same member, listing and message) is treated as a double submit.
     */
    private const DUPLICATE_WINDOW_SECONDS = 60;

    /**
     * @return LengthAwarePaginator<int, RouteListingInquiry>
     */
    public function paginate(?string $search = null, ?RouteInquiryStatus $status = null, int $perPage = 15): LengthAwarePaginator
    {
        $search = trim((string) $search);

        return RouteListingInquiry::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('contact_name', 'like', "%{$search}%")
                        ->orWhere('contact_email', 'like', "%{$search}%")
                        ->orWhere('listing_reference', 'like', "%{$search}%")
                        ->orWhere('listing_city', 'like', "%{$search}%");
                });
            })
            ->when($status !== null, fn ($query) => $query->where('status', $status?->value))
            ->latest()
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Save a member's inquiry about a listing and notify the business.
     *
     * The member comes from the authenticated session, never from request input. A repeat of the
     * same inquiry within a minute returns the original instead of creating (and emailing) another.
     *
     * @param  array{contact_name: string, contact_email: string, contact_phone?: string|null, message: string}  $data
     */
    public function create(User $member, RouteListing $listing, array $data): RouteListingInquiry
    {
        $existing = RouteListingInquiry::query()
            ->where('user_id', $member->id)
            ->where('route_listing_id', $listing->id)
            ->where('message', $data['message'])
            ->where('created_at', '>=', now()->subSeconds(self::DUPLICATE_WINDOW_SECONDS))
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $inquiry = RouteListingInquiry::create([
            'route_listing_id' => $listing->id,
            'user_id' => $member->id,
            'contact_name' => $data['contact_name'],
            'contact_email' => $data['contact_email'],
            'contact_phone' => $data['contact_phone'] ?? null,
            'listing_reference' => 'R-'.$listing->id,
            'listing_city' => $listing->city,
            'listing_asking_price' => $listing->asking_price,
            'message' => $data['message'],
            'status' => RouteInquiryStatus::PENDING,
        ]);

        $this->notifyBusiness($inquiry);

        return $inquiry;
    }

    public function updateStatus(RouteListingInquiry $inquiry, RouteInquiryStatus $status): RouteListingInquiry
    {
        $inquiry->update(['status' => $status]);

        return $inquiry->refresh();
    }

    /**
     * A missing address or a mail failure must never undo the saved inquiry.
     */
    private function notifyBusiness(RouteListingInquiry $inquiry): void
    {
        $recipient = config('route-listings.inquiry_notification_email');

        if (! is_string($recipient) || trim($recipient) === '') {
            Log::warning('Route inquiry saved but no notification email is configured (ROUTE_INQUIRY_NOTIFICATION_EMAIL).', [
                'inquiry_id' => $inquiry->id,
            ]);

            return;
        }

        try {
            Mail::to($recipient)->queue(new RouteInquiryReceivedMail($inquiry));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
