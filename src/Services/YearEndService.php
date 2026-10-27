<?php

namespace SCMS\Finance\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use SCMS\Finance\Enums\AccountType;
use SCMS\Finance\Enums\EntryStatus;
use SCMS\Finance\Enums\LineComponent;
use SCMS\Finance\Enums\YearStatus;
use SCMS\Finance\Exceptions\FinanceException;
use SCMS\Finance\Exceptions\InvalidStateException;
use SCMS\Finance\Models\Account;
use SCMS\Finance\Models\Expense;
use SCMS\Finance\Models\Income;
use SCMS\Finance\Models\JournalEntry;
use SCMS\Finance\Models\Transfer;
use SCMS\Finance\Support\AccountMap;
use SCMS\Finance\Support\FinanceModels;
use SCMS\Finance\Support\Money;

/**
 * Closes a financial year: per branch, one closing entry dated the last day moves every income
 * and expense balance of the year to Accumulated surplus; then the year is closed and takes no
 * more postings. Balance sheet accounts need no opening entry: their balance is cumulative.
 */
class YearEndService
{
    public function __construct(
        private readonly JournalService $journal,
        private readonly LedgerService $ledger,
        private readonly AccountMap $accounts,
    ) {
    }

    /** @return list<JournalEntry> the closing entries */
    public function close(Model $year, ?Authenticatable $user): array
    {
        return DB::transaction(function () use ($year, $user) {
            $year = FinanceModels::financialYear()::query()->lockForUpdate()->findOrFail($year->getKey());
            if ($year->status !== YearStatus::Open && $year->status !== YearStatus::Open->value) {
                throw new InvalidStateException(__('The financial year :name is already closed.', ['name' => $year->name]));
            }

            $drafts = Income::query()->withoutBranchScope()->where('financial_year_id', $year->getKey())->where('status', EntryStatus::Draft->value)->count()
                + Expense::query()->withoutBranchScope()->where('financial_year_id', $year->getKey())->where('status', EntryStatus::Draft->value)->count()
                + Transfer::query()->withoutBranchScope()->where('financial_year_id', $year->getKey())->where('status', EntryStatus::Draft->value)->count()
                + JournalEntry::query()->withoutBranchScope()->where('financial_year_id', $year->getKey())->where('status', EntryStatus::Draft->value)->count();
            if ($drafts > 0) {
                throw new FinanceException(__(':n drafts are dated in this year; post or cancel them first.', ['n' => $drafts]));
            }

            $surplus = $this->accounts->get('accumulated_surplus');
            $totals = $this->ledger->lines(null, $year->starts_on, $year->ends_on)
                ->join('finance_accounts as a', 'a.id', '=', 'l.account_id')
                ->whereIn('a.type', [AccountType::Income->value, AccountType::Expense->value])
                ->groupBy('e.branch_id', 'l.account_id')
                ->selectRaw('e.branch_id, l.account_id, SUM(l.debit) - SUM(l.credit) as net')
                ->get()
                ->groupBy('branch_id');

            $entries = [];
            foreach ($totals as $branchId => $rows) {
                $accounts = Account::query()->whereKey($rows->pluck('account_id')->all())->get()->keyBy('id');
                $lines = [];
                $result = Money::zero(); // debit minus credit moved out of income / expense
                foreach ($rows as $row) {
                    $net = Money::of((string) $row->net);
                    if ($net->isZero()) {
                        continue;
                    }
                    $account = $accounts[$row->account_id];
                    // The closing line is the opposite of the balance, so the account ends at zero.
                    $lines[] = $net->isPositive()
                        ? JournalService::credit($account, $net, ['component' => LineComponent::Closing])
                        : JournalService::debit($account, $net->negated(), ['component' => LineComponent::Closing]);
                    $result = $result->plus($net);
                }
                if ($lines === []) {
                    continue;
                }
                // Income above expense leaves a credit to the surplus; a deficit a debit.
                $lines[] = $result->isPositive()
                    ? JournalService::debit($surplus, $result, ['component' => LineComponent::Closing])
                    : JournalService::credit($surplus, $result->negated(), ['component' => LineComponent::Closing]);
                if ($result->isZero()) {
                    array_pop($lines);
                }

                $entries[] = $this->journal->record([
                    'branch_id' => (int) $branchId,
                    'entry_date' => $year->ends_on,
                    'description' => __('Closing of financial year :name', ['name' => $year->name]),
                    'source_type' => 'year_close',
                    'source_id' => $year->getKey(),
                    'number_key' => 'year_close',
                ], $lines, null);
            }

            $year->forceFill(['status' => YearStatus::Closed->value, 'closed_at' => now(), 'closed_by' => $user?->getAuthIdentifier()])->save();

            return $entries;
        });
    }
}
