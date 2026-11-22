<?php

namespace App\Services;

use App\Mail\MembershipCustomPriceMail;
use App\Models\MembershipCustomPrice;
use App\Models\MembershipPriceHistory;
use App\Models\OperatorMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Decides what a member pays: the default monthly price, or a custom price agreed for that person.
 *
 * A member with a custom price pays it in any mode (even while membership is free for everyone else)
 * and never pays the default price. When the custom price is removed the member pays the current default
 * price from then on (also while membership is free for everyone else).
 */
class MembershipPricingService
{
    public const TYPE_FREE = 'free';

    public const TYPE_PAID = 'paid';

    public const TYPE_CUSTOM = 'custom';

    public function __construct(private MembershipSettingsService $settings) {}

    /**
     * The custom price row for this member, matched by account first and then by email.
     * A row reserved by email is linked to the account the first time it is found.
     */
    public function customRowFor(User $user): ?MembershipCustomPrice
    {
        $row = MembershipCustomPrice::query()->where('user_id', $user->id)->first();

        if ($row !== null) {
            return $row;
        }

        $row = MembershipCustomPrice::query()
            ->whereNull('user_id')
            ->where('email', MembershipCustomPrice::normalizeEmail($user->email))
            ->first();

        if ($row !== null) {
            // Linking the account is not a price change, so "updated at" stays as it was.
            MembershipCustomPrice::withoutTimestamps(fn () => $row->forceFill(['user_id' => $user->id])->save());
        }

        return $row;
    }

    public function customPriceFor(User $user): ?int
    {
        return $this->customRowFor($user)?->monthly_price;
    }

    /**
     * Whether the member was moved to the default price when their custom price was removed.
     */
    public function paysDefaultPrice(User $user): bool
    {
        return OperatorMembership::query()
            ->where('user_id', $user->id)
            ->where('pays_default_price', true)
            ->exists();
    }

    /**
     * Whether this member has to pay to use the marketplace.
     */
    public function requiresPayment(User $user): bool
    {
        return $this->customPriceFor($user) !== null
            || $this->settings->isPaid()
            || $this->paysDefaultPrice($user);
    }

    public function priceFor(User $user): int
    {
        return $this->customPriceFor($user) ?? $this->settings->monthlyPrice();
    }

    /**
     * @return 'free'|'paid'|'custom'
     */
    public function typeFor(User $user): string
    {
        return match (true) {
            $this->customPriceFor($user) !== null => self::TYPE_CUSTOM,
            $this->settings->isPaid() || $this->paysDefaultPrice($user) => self::TYPE_PAID,
            default => self::TYPE_FREE,
        };
    }

    /**
     * What the admin sees when typing an email: whether a member exists and which membership they are on.
     *
     * @return array{exists: bool, name: string|null, message: string}
     */
    public function lookup(string $email): array
    {
        $email = MembershipCustomPrice::normalizeEmail($email);
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user === null) {
            $reserved = MembershipCustomPrice::query()->where('email', $email)->first();

            return [
                'exists' => false,
                'name' => null,
                'message' => $reserved !== null
                    ? "No member with this email yet. A custom price of \${$reserved->monthly_price}/mo is already reserved for it; saving updates it."
                    : 'No member with this email yet. The price will apply when they register.',
            ];
        }

        return [
            'exists' => true,
            'name' => $user->name,
            'message' => "{$user->name} is already on: {$this->describe($user)}.",
        ];
    }

    /**
     * The membership a member is currently on, in words, e.g. "Paid monthly ($29/mo, active until Dec 5)".
     */
    public function describe(User $user): string
    {
        $membership = OperatorMembership::query()->where('user_id', $user->id)->first();
        $type = $this->typeFor($user);
        $price = $this->priceFor($user);

        $paidPrice = $membership?->hasValidPaidPeriod() ? $membership->monthly_price : null;

        $status = match (true) {
            $membership === null => '',
            $paidPrice !== null && $paidPrice !== $price && $membership->current_period_end !== null => ", from next renewal; paying \${$paidPrice}/mo until {$membership->current_period_end->format('M j')}",
            $membership->status === OperatorMembership::STATUS_ACTIVE && $membership->current_period_end !== null => ", active until {$membership->current_period_end->format('M j')}",
            $membership->status === OperatorMembership::STATUS_REQUIRED => ', payment required',
            $membership->status === OperatorMembership::STATUS_PAST_DUE => ', past due',
            $membership->status === OperatorMembership::STATUS_CANCELED => ', canceled',
            default => '',
        };

        return match ($type) {
            self::TYPE_CUSTOM => "Custom price (\${$price}/mo{$status})",
            self::TYPE_PAID => "Paid monthly (\${$price}/mo{$status})",
            default => 'Free membership',
        };
    }

    /**
     * Save a custom price for an email, whether or not that person has an account yet.
     * Every real change is written to the price history.
     */
    public function set(string $email, int $price, ?User $admin = null): MembershipCustomPrice
    {
        $email = MembershipCustomPrice::normalizeEmail($email);

        return DB::transaction(function () use ($email, $price, $admin): MembershipCustomPrice {
            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

            $row = MembershipCustomPrice::query()->firstOrNew(['email' => $email]);
            $oldPrice = $row->exists ? $row->monthly_price : null;

            $row->fill([
                'monthly_price' => $price,
                'user_id' => $user?->id,
                'created_by' => $row->created_by ?? $admin?->id,
            ])->save();

            if ($oldPrice !== $price) {
                $this->recordHistory(
                    $email,
                    $user?->id,
                    $oldPrice === null ? MembershipPriceHistory::ACTION_CREATED : MembershipPriceHistory::ACTION_UPDATED,
                    $oldPrice,
                    $price,
                    $admin,
                );
            }

            if ($user !== null) {
                $this->applyToMembership($user, $price);
            }

            return $row;
        });
    }

    /**
     * Remove the custom price. A member with an account moves to the current default price and has to pay it,
     * also while membership is free for everyone else; an email without an account is simply forgotten.
     */
    public function remove(MembershipCustomPrice $row, ?User $admin = null): void
    {
        DB::transaction(function () use ($row, $admin): void {
            $userId = $row->user_id;
            $defaultPrice = $this->settings->monthlyPrice();

            $row->delete();

            $this->recordHistory(
                $row->email,
                $userId,
                MembershipPriceHistory::ACTION_REMOVED,
                $row->monthly_price,
                $userId !== null ? $defaultPrice : null,
                $admin,
            );

            if ($userId === null) {
                return;
            }

            $membership = OperatorMembership::query()->where('user_id', $userId)->lockForUpdate()->first()
                ?? new OperatorMembership(['user_id' => $userId, 'status' => OperatorMembership::STATUS_ACTIVE_FREE]);

            $membership->forceFill([
                // While a paid period is running, monthly_price stays the price that was paid for it.
                'monthly_price' => $membership->hasValidPaidPeriod() ? $membership->monthly_price : $defaultPrice,
                'pays_default_price' => true,
                'status' => $membership->status === OperatorMembership::STATUS_ACTIVE_FREE
                    ? OperatorMembership::STATUS_REQUIRED
                    : $membership->status,
            ])->save();
        });
    }

    /**
     * Email the member about their price (an invitation to register when there is no account yet).
     * A mail failure never undoes the saved price.
     */
    public function notify(MembershipCustomPrice $row): bool
    {
        $name = $row->user_id !== null ? User::query()->whereKey($row->user_id)->value('name') : null;

        $paidPeriod = $row->user_id !== null
            ? OperatorMembership::query()->where('user_id', $row->user_id)->first()
            : null;
        $appliesFrom = $paidPeriod?->hasValidPaidPeriod() && $paidPeriod->monthly_price !== $row->monthly_price
            ? ($paidPeriod->next_billing_at ?? $paidPeriod->current_period_end)?->format('F j, Y')
            : null;

        try {
            Mail::to($row->email)->queue(new MembershipCustomPriceMail(
                price: $row->monthly_price,
                name: is_string($name) ? $name : null,
                registered: $row->user_id !== null,
                appliesFrom: $appliesFrom,
            ));
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }

        $row->forceFill(['notified_at' => now()])->save();

        MembershipPriceHistory::query()
            ->where('email', $row->email)
            ->latest('id')
            ->limit(1)
            ->update(['email_sent' => true]);

        return true;
    }

    private function recordHistory(
        string $email,
        ?int $userId,
        string $action,
        ?int $oldPrice,
        ?int $newPrice,
        ?User $admin,
    ): void {
        MembershipPriceHistory::query()->create([
            'email' => $email,
            'user_id' => $userId,
            'action' => $action,
            'old_price' => $oldPrice,
            'new_price' => $newPrice,
            'changed_by' => $admin?->id,
        ]);
    }

    /**
     * A member who is already on a free membership must pay from now on; a paying member keeps the
     * current paid period and the new price applies from the next renewal.
     */
    private function applyToMembership(User $user, int $price): void
    {
        $membership = OperatorMembership::query()->where('user_id', $user->id)->lockForUpdate()->first();

        if ($membership === null) {
            return;
        }

        $updates = ['pays_default_price' => false];

        // While a paid period is running, monthly_price stays the price that was paid for it.
        if (! $membership->hasValidPaidPeriod()) {
            $updates['monthly_price'] = $price;
        }

        if ($membership->status === OperatorMembership::STATUS_ACTIVE_FREE) {
            $updates['status'] = OperatorMembership::STATUS_REQUIRED;
        }

        $membership->forceFill($updates)->save();
    }
}
