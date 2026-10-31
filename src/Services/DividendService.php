<?php

namespace SCMS\Finance\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use SCMS\Finance\Enums\EntryStatus;
use SCMS\Finance\Enums\SavingsAccountStatus;
use SCMS\Finance\Enums\YearStatus;
use SCMS\Finance\Exceptions\FinanceException;
use SCMS\Finance\Models\DividendRun;
use SCMS\Finance\Models\SavingsAccount;
use SCMS\Finance\Models\SavingsScheme;
use SCMS\Finance\Support\AccountMap;
use SCMS\Finance\Support\Money;

/**
 * Dividend on share capital for a closed year: each member's share balance on the last day of the
 * year × rate, credited to their active savings account of the chosen scheme, out of the
 * accumulated surplus of the account's branch. A year is distributed once.
 */
class DividendService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly FinancialYearService $years,
        private readonly SavingsTransactionService $transactions,
        private readonly AccountMap $accounts,
    ) {
    }

    /**
     * @return array{rows: Collection<int, array{member_id: int, shares: Money, dividend: Money, account: SavingsAccount}>, skipped: list<int>}
     */
    public function preview(Model $year, string $rate, SavingsScheme $scheme, ?array $branchIds = null): array
    {
        $balances = $this->ledger->lines($branchIds, null, $year->ends_on)
            ->where('l.account_id', $this->accounts->get('share_capital')->id)
            ->whereNotNull('l.member_id')
            ->groupBy('l.member_id')
            ->selectRaw('l.member_id, SUM(l.credit) - SUM(l.debit) as shares')
            ->havingRaw('SUM(l.credit) - SUM(l.debit) > 0')
            ->pluck('shares', 'member_id');

        $accounts = SavingsAccount::query()->withoutBranchScope()
            ->where('savings_scheme_id', $scheme->id)->where('status', SavingsAccountStatus::Active->value)
            ->whereIn('member_id', $balances->keys())->orderBy('id')->get()->unique('member_id')->keyBy('member_id');

        $rows = collect();
        $skipped = [];
        foreach ($balances as $memberId => $shares) {
            $dividend = Money::of((string) $shares)->times($rate)->dividedBy(100);
            if (! $dividend->isPositive()) {
                continue;
            }
            if (! $accounts->has($memberId)) {
                $skipped[] = (int) $memberId;

                continue;
            }
            $rows->push(['member_id' => (int) $memberId, 'shares' => Money::of((string) $shares), 'dividend' => $dividend, 'account' => $accounts[$memberId]]);
        }

        return ['rows' => $rows, 'skipped' => $skipped];
    }

    public function post(Model $year, string $rate, SavingsScheme $scheme, Carbon $postingDate, ?Authenticatable $user): DividendRun
    {
        if (! Money::of($rate)->isPositive()) {
            throw new FinanceException(__('Give the dividend rate.'));
        }
        $status = $year->status instanceof YearStatus ? $year->status : YearStatus::tryFrom((string) $year->status);
        if ($status !== YearStatus::Closed) {
            throw new FinanceException(__('Close the financial year first: the dividend comes out of its surplus.'));
        }
        if ($postingDate->lte(Carbon::parse($year->ends_on))) {
            throw new FinanceException(__('The dividend is posted after the year it is for.'));
        }
        $this->years->assertPostingDate($postingDate);

        return DB::transaction(function () use ($year, $rate, $scheme, $postingDate, $user) {
            DB::table('financial_years')->where('id', $year->getKey())->lockForUpdate()->first();
            $done = DividendRun::query()->where('financial_year_id', $year->getKey())
                ->whereHas('transactions', fn ($q) => $q->where('status', EntryStatus::Posted->value))->exists();
            if ($done) {
                throw new FinanceException(__('The dividend of :year was already distributed.', ['year' => $year->name]));
            }

            $preview = $this->preview($year, $rate, $scheme);
            if ($preview['rows']->isEmpty()) {
                throw new FinanceException(__('No member with shares has an active account in :scheme.', ['scheme' => $scheme->name]));
            }

            // Each branch pays out of its own surplus.
            $surplus = $this->accounts->get('accumulated_surplus');
            foreach ($preview['rows']->groupBy(fn ($r) => $r['account']->branch_id) as $branchId => $rows) {
                $available = $this->ledger->accountBalance($surplus, [(int) $branchId], $postingDate);
                $needed = Money::sum($rows->pluck('dividend')->all());
                if ($needed->greaterThan($available)) {
                    throw new FinanceException(__('The dividend of a branch (:needed) is more than its accumulated surplus (:available).', ['needed' => $needed->format(), 'available' => $available->format()]));
                }
            }

            $run = new DividendRun();
            $run->forceFill(['financial_year_id' => $year->getKey(), 'savings_scheme_id' => $scheme->id, 'rate' => $rate,
                'posting_date' => $postingDate->toDateString(), 'created_by' => $user?->getAuthIdentifier()])->save();

            foreach ($preview['rows'] as $row) {
                $this->transactions->creditDividend($row['account'], $row['dividend'], $postingDate->toDateString(), $run->id, $user);
            }

            $run->forceFill(['total' => Money::sum($preview['rows']->pluck('dividend')->all())->toString(), 'accounts_count' => $preview['rows']->count()])->save();
            \Nit\Master\Support\Activity::log('finance.dividend.posted', "Dividend of {$run->total} ({$rate}%) for {$year->name} on {$run->accounts_count} accounts", $run);

            return $run;
        });
    }
}
