<?php

namespace App\Services;

use App\Enums\LeadIssueCategory;
use App\Enums\LeadIssueStatus;
use App\Mail\LeadIssueReportedMail;
use App\Models\CoffeeServiceRequest;
use App\Models\LeadIssueReport;
use App\Models\LeadPurchase;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Operators send a purchased lead back to the admin with a written reason; the admin approves or rejects it.
 * Nothing is refunded here: money and the lead itself are not touched.
 */
class LeadIssueReportService
{
    public function __construct(private LeadIssueSettingsService $settings) {}

    /**
     * @return LengthAwarePaginator<int, LeadIssueReport>
     */
    public function paginate(
        ?string $search = null,
        ?LeadIssueStatus $status = null,
        ?LeadIssueCategory $category = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        $search = trim((string) $search);

        return LeadIssueReport::query()
            ->with('user:id,name,email')
            ->when($search !== '', function ($query) use ($search): void {
                $term = '%'.addcslashes($search, '\\%_').'%';

                $query->where(function ($query) use ($term): void {
                    $query->where('lead_reference', 'like', $term)
                        ->orWhere('lead_name', 'like', $term)
                        ->orWhere('lead_city', 'like', $term)
                        ->orWhereHas('user', function ($query) use ($term): void {
                            $query->where('name', 'like', $term)->orWhere('email', 'like', $term);
                        });
                });
            })
            ->when($status !== null, fn ($query) => $query->where('status', $status?->value))
            ->when($category !== null, fn ($query) => $query->where('category', $category?->value))
            ->latest()
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Whether the operator can still send this purchase back: it is paid, has no request yet and is inside
     * the time window the admin set (no window = no limit).
     */
    public function canReport(LeadPurchase $purchase, bool $alreadyReported = false): bool
    {
        return $this->canReportWithin($purchase, $alreadyReported, $this->settings->reportWindowDays());
    }

    /**
     * Same as {@see self::canReport()} with the window already known, so a list of purchases reads it once.
     */
    public function canReportWithin(LeadPurchase $purchase, bool $alreadyReported, ?int $days): bool
    {
        if (! $purchase->isPaid() || $alreadyReported) {
            return false;
        }

        if ($days === null) {
            return true;
        }

        $boughtAt = $purchase->purchased_at ?? $purchase->paid_at ?? $purchase->created_at;

        return $boughtAt === null || $boughtAt->greaterThanOrEqualTo(now()->subDays($days));
    }

    /**
     * @param  array{category: string, reason: string}  $data
     *
     * @throws ValidationException
     */
    public function create(User $operator, LeadPurchase $purchase, array $data): LeadIssueReport
    {
        abort_unless($purchase->user_id === $operator->id, 404);

        $report = DB::transaction(function () use ($operator, $purchase, $data): LeadIssueReport {
            $locked = LeadPurchase::query()->lockForUpdate()->findOrFail($purchase->id);
            $alreadyReported = LeadIssueReport::query()->where('lead_purchase_id', $locked->id)->exists();

            if ($alreadyReported) {
                throw ValidationException::withMessages(['reason' => 'You have already sent this lead back.']);
            }

            if (! $locked->isPaid()) {
                throw ValidationException::withMessages(['reason' => 'Only a paid lead can be sent back.']);
            }

            if (! $this->canReport($locked)) {
                throw ValidationException::withMessages([
                    'reason' => 'The time to send this lead back has passed.',
                ]);
            }

            $lead = $locked->leadable;

            return LeadIssueReport::query()->create([
                'lead_purchase_id' => $locked->id,
                'user_id' => $operator->id,
                'category' => $data['category'],
                'reason' => trim($data['reason']),
                'status' => LeadIssueStatus::OPEN,
                'lead_reference' => $lead !== null
                    ? $this->leadReference($locked, $lead)
                    : strtoupper(substr($locked->leadable_type, 0, 1)).'-'.$locked->leadable_id,
                'lead_name' => $lead?->business_name,
                'lead_type' => $lead instanceof CoffeeServiceRequest
                    ? 'Office coffee'
                    : $lead?->locationTypeLabel(),
                'lead_city' => $lead?->city,
                'price' => $locked->price,
                'purchased_at' => $locked->purchased_at ?? $locked->paid_at ?? $locked->created_at,
            ]);
        });

        $this->notifyAdmin($report);

        return $report;
    }

    /**
     * Approve or reject a request. This only records the decision.
     */
    public function resolve(LeadIssueReport $report, LeadIssueStatus $status, string $note, User $admin): LeadIssueReport
    {
        $report->update([
            'status' => $status,
            'admin_note' => trim($note),
            'resolved_by' => $admin->id,
            'resolved_at' => now(),
        ]);

        return $report->refresh();
    }

    private function leadReference(LeadPurchase $purchase, Model $lead): string
    {
        return LeadService::publicIdFor($purchase->leadable_type, (int) $lead->getKey());
    }

    /**
     * A missing address or a mail failure must never undo the saved request.
     */
    private function notifyAdmin(LeadIssueReport $report): void
    {
        $recipient = config('leads.issue_notification_email');

        if (! is_string($recipient) || trim($recipient) === '') {
            Log::warning('Lead return request saved but no notification email is configured (LEAD_ISSUE_NOTIFICATION_EMAIL).', [
                'report_id' => $report->id,
            ]);

            return;
        }

        try {
            Mail::to($recipient)->queue(new LeadIssueReportedMail($report));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
