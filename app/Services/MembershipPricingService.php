<?php

namespace App\Services;

use App\Mail\MembershipCustomPriceMail;
use App\Models\MembershipCustomPrice;
use App\Models\OperatorMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Decides what a member pays: the default monthly price, or a custom price agreed for that person.
 *
 * A member with a custom price pays it in any mode (even while membership is free for everyone else)
 * and never pays the default price.
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

        $row?->forceFill(['user_id' => $user->id])->save();

        return $row;
    }

    public function customPriceFor(User $user): ?int
    {
        return $this->customRowFor($user)?->monthly_price;
    }

    /**
     * Whether this member has to pay to use the marketplace.
     */
    public function requiresPayment(User $user): bool
    {
        return $this->customPriceFor($user) !== null || $this->settings->isPaid();
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
            $this->settings->isPaid() => self::TYPE_PAID,
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

        $status = match (true) {
            $membership === null => '',
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
     */
    public function set(string $email, int $price, ?User $admin = null): MembershipCustomPrice
    {
        $email = MembershipCustomPrice::normalizeEmail($email);

        return DB::transaction(function () use ($email, $price, $admin): MembershipCustomPrice {
            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

            $row = MembershipCustomPrice::query()->firstOrNew(['email' => $email]);
            $row->fill([
                'monthly_price' => $price,
                'user_id' => $user?->id,
                'created_by' => $row->created_by ?? $admin?->id,
            ])->save();

            if ($user !== null) {
                $this->applyToMembership($user, $price);
            }

            return $row;
        });
    }

    /**
     * Remove the custom price: the member goes back to the default membership.
     */
    public function remove(MembershipCustomPrice $row): void
    {
        DB::transaction(function () use ($row): void {
            $userId = $row->user_id;
            $row->delete();

            if ($userId === null) {
                return;
            }

            $membership = OperatorMembership::query()->where('user_id', $userId)->lockForUpdate()->first();

            if ($membership === null) {
                return;
            }

            $updates = ['monthly_price' => $this->settings->monthlyPrice()];

            if ($this->settings->isFree() && $membership->status === OperatorMembership::STATUS_REQUIRED) {
                $updates['status'] = OperatorMembership::STATUS_ACTIVE_FREE;
            }

            $membership->forceFill($updates)->save();
        });
    }

    /**
     * Email the member about their price (an invitation to register when there is no account yet).
     * A mail failure never undoes the saved price.
     */
    public function notify(MembershipCustomPrice $row): bool
    {
        $name = $row->user_id !== null ? User::query()->whereKey($row->user_id)->value('name') : null;

        try {
            Mail::to($row->email)->queue(new MembershipCustomPriceMail(
                price: $row->monthly_price,
                name: is_string($name) ? $name : null,
                registered: $row->user_id !== null,
            ));
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }

        $row->forceFill(['notified_at' => now()])->save();

        return true;
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

        $updates = ['monthly_price' => $price];

        if ($membership->status === OperatorMembership::STATUS_ACTIVE_FREE) {
            $updates['status'] = OperatorMembership::STATUS_REQUIRED;
        }

        $membership->forceFill($updates)->save();
    }
}
