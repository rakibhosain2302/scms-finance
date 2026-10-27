<?php

namespace SCMS\Finance\Services;

use Brick\Math\BigDecimal;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use SCMS\Finance\Enums\EntryStatus;
use SCMS\Finance\Enums\SavingsAccountStatus;
use SCMS\Finance\Events\SavingsInterestPosted;
use SCMS\Finance\Exceptions\FinanceException;
use SCMS\Finance\Models\SavingsAccount;
use SCMS\Finance\Models\SavingsInterestRun;
use SCMS\Finance\Models\SavingsScheme;
use SCMS\Finance\Support\Money;

/**
 * Interest on savings, for one scheme in one branch and a period, on the daily balance:
 *
 *     interest = Σ (balance at the end of each day × 1 day) × annual rate / 100 / 365
 *
 * A negative balance earns nothing. A period is credited once: a run overlapping one that still
 * has posted interest is refused (reversing every transaction of a run frees its period).
 */
class SavingsInterestService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly FinancialYearService $years,
        private readonly JournalService $journal,
        private readonly SavingsTransactionService $transactions,
    ) {
    }

    /**
     * What each account would get, without posting anything.
     *
     * @return Collection<int, array{account: SavingsAccount, interest: Money}>
     */
    public function preview(SavingsScheme $scheme, int $branchId, Carbon $from, Carbon $to): Collection
    {
        $rate = $this->rate($scheme);

        return $this->accounts($scheme, $branchId, $from, $to)
            ->map(fn (SavingsAccount $account) => ['account' => $account, 'interest' => $this->interestFor($account->id, $from, $to, $rate)])
            ->filter(fn (array $row) => $row['interest']->isPositive())
            ->values();
    }

    public function post(SavingsScheme $scheme, int $branchId, Carbon $from, Carbon $to, Carbon $postingDate, ?Authenticatable $user): SavingsInterestRun
    {
        $this->journal->assertBranch($user, $branchId);
        if ($to->lt($from)) {
            throw new FinanceException(__('The period must end after it starts.'));
        }
        if ($postingDate->lt($to)) {
            throw new FinanceException(__('Interest is posted on or after the last day of the period.'));
        }
        $this->years->assertPostingDate($postingDate);
        $rate = $this->rate($scheme);

        $run = DB::transaction(function () use ($scheme, $branchId, $from, $to, $postingDate, $user, $rate) {
            // One run per scheme at a time: the scheme row is the lock.
            SavingsScheme::query()->lockForUpdate()->findOrFail($scheme->id);
            $this->assertPeriodFree($scheme, $branchId, $from, $to);

            $run = new SavingsInterestRun();
            $run->forceFill([
                'branch_id' => $branchId,
                'savings_scheme_id' => $scheme->id,
                'period_from' => $from->toDateString(),
                'period_to' => $to->toDateString(),
                'posting_date' => $postingDate->toDateString(),
                'rate' => $rate->__toString(),
                'created_by' => $user?->getAuthIdentifier(),
            ])->save();

            $total = Money::zero();
            $count = 0;
            foreach ($this->accounts($scheme, $branchId, $from, $to) as $account) {
                $interest = $this->interestFor($account->id, $from, $to, $rate);
                if (! $interest->isPositive()) {
                    continue;
                }
                $this->transactions->creditInterest($account, $interest, $postingDate->toDateString(), $run->id, $user);
                $total = $total->plus($interest);
                $count++;
            }

            if ($count === 0) {
                throw new FinanceException(__('No account earned interest in this period.'));
            }

            $run->forceFill(['total' => $total->toString(), 'accounts_count' => $count])->save();

            return $run;
        });

        event(new SavingsInterestPosted($run, $user?->getAuthIdentifier()));

        return $run;
    }

    /** Interest on the daily closing balances from $from to $to, both days included. */
    public function interestFor(int $accountId, Carbon $from, Carbon $to, BigDecimal $rate): Money
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();
        $balance = BigDecimal::of($this->ledger->savingsBalance($accountId, $from->copy()->subDay())->toString());

        $movements = $this->ledger->lines(null, $from, $to)
            ->where('l.savings_account_id', $accountId)
            ->groupBy('e.entry_date')->orderBy('e.entry_date')
            ->selectRaw('e.entry_date, SUM(l.credit) - SUM(l.debit) as net')
            ->get();

        $balanceDays = BigDecimal::zero();
        $cursor = $from;
        foreach ($movements as $movement) {
            $day = Carbon::parse($movement->entry_date)->startOfDay();
            $balanceDays = $balanceDays->plus($this->positive($balance)->multipliedBy((int) $cursor->diffInDays($day)));
            $balance = $balance->plus((string) $movement->net);
            $cursor = $day;
        }
        $balanceDays = $balanceDays->plus($this->positive($balance)->multipliedBy((int) $cursor->diffInDays($to) + 1));

        return Money::of($balanceDays->multipliedBy($rate)->dividedBy(36500, 10, Money::halfUp()));
    }

    /** @return Collection<int, SavingsAccount> accounts of the scheme open at some point of the period */
    private function accounts(SavingsScheme $scheme, int $branchId, Carbon $from, Carbon $to): Collection
    {
        return SavingsAccount::query()->withoutBranchScope()
            ->where('savings_scheme_id', $scheme->id)
            ->where('branch_id', $branchId)
            ->whereDate('opened_on', '<=', $to->toDateString())
            ->where(fn ($q) => $q->where('status', '!=', SavingsAccountStatus::Closed->value)->orWhereDate('closed_on', '>=', $from->toDateString()))
            ->orderBy('id')
            ->get();
    }

    private function rate(SavingsScheme $scheme): BigDecimal
    {
        $rate = BigDecimal::of((string) ($scheme->interest_rate ?? '0'));
        if (! $rate->isPositive()) {
            throw new FinanceException(__(':scheme pays no interest; set its rate first.', ['scheme' => $scheme->name]));
        }

        return $rate;
    }

    private function assertPeriodFree(SavingsScheme $scheme, int $branchId, Carbon $from, Carbon $to): void
    {
        $overlap = SavingsInterestRun::query()->withoutBranchScope()
            ->where('savings_scheme_id', $scheme->id)
            ->where('branch_id', $branchId)
            ->whereDate('period_from', '<=', $to->toDateString())
            ->whereDate('period_to', '>=', $from->toDateString())
            ->whereHas('transactions', fn ($q) => $q->where('status', EntryStatus::Posted->value))
            ->first();

        if ($overlap) {
            throw new FinanceException(__('Interest for :from – :to was already posted.', ['from' => $overlap->period_from->format('j M Y'), 'to' => $overlap->period_to->format('j M Y')]));
        }
    }

    private function positive(BigDecimal $amount): BigDecimal
    {
        return $amount->isNegative() ? BigDecimal::zero() : $amount;
    }
}
