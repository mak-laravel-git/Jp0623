<?php

namespace App\Services;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\LeadIssueReport;
use App\Models\LeadPurchase;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * A member's Credits (1 Credit = $1, no expiry, lead purchases only).
 *
 * `credit_transactions` is the history; `users.credit_balance` is the running total. Both change together
 * inside one transaction while the user row is locked, so two purchases can never spend the same Credits.
 * Call these methods from inside the caller's transaction when the change must succeed or fail with it.
 */
class CreditService
{
    public function balanceFor(User $user): int
    {
        return (int) User::query()->whereKey($user->id)->value('credit_balance');
    }

    /**
     * Credits the member can use on a lead that costs `$price` dollars.
     */
    public function usableFor(User $user, int $price): int
    {
        return min($this->balanceFor($user), max(0, $price));
    }

    /**
     * Add Credits to a member (a refund given as Credits, or Credits returned after a failed purchase).
     */
    public function grant(
        User $user,
        int $amount,
        CreditTransactionType $type,
        ?LeadPurchase $purchase = null,
        ?LeadIssueReport $report = null,
        ?User $by = null,
        ?string $note = null,
    ): CreditTransaction {
        if ($amount < 1) {
            throw new InvalidArgumentException('Credits to add must be at least 1.');
        }

        return $this->record($user, $amount, $type, $purchase, $report, $by, $note);
    }

    /**
     * Take Credits from a member. Fails if they do not have enough.
     */
    public function spend(
        User $user,
        int $amount,
        ?LeadPurchase $purchase = null,
        ?string $note = null,
    ): CreditTransaction {
        if ($amount < 1) {
            throw new InvalidArgumentException('Credits to spend must be at least 1.');
        }

        return $this->record($user, -$amount, CreditTransactionType::PURCHASE_SPEND, $purchase, null, null, $note);
    }

    /**
     * Newest first.
     *
     * @return Collection<int, CreditTransaction>
     */
    public function historyFor(User $user, int $limit = 50): Collection
    {
        return CreditTransaction::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    private function record(
        User $user,
        int $signedAmount,
        CreditTransactionType $type,
        ?LeadPurchase $purchase,
        ?LeadIssueReport $report,
        ?User $by,
        ?string $note,
    ): CreditTransaction {
        return DB::transaction(function () use ($user, $signedAmount, $type, $purchase, $report, $by, $note): CreditTransaction {
            $balance = (int) User::query()->lockForUpdate()->whereKey($user->id)->value('credit_balance');
            $next = $balance + $signedAmount;

            if ($next < 0) {
                throw new InvalidArgumentException('Not enough Credits.');
            }

            User::query()->whereKey($user->id)->update(['credit_balance' => $next]);

            return CreditTransaction::query()->create([
                'user_id' => $user->id,
                'type' => $type,
                'amount' => $signedAmount,
                'balance_after' => $next,
                'lead_purchase_id' => $purchase?->id,
                'lead_issue_report_id' => $report?->id,
                'created_by' => $by?->id,
                'note' => $note,
            ]);
        });
    }
}
