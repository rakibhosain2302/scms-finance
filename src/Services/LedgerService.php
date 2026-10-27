<?php

namespace SCMS\Finance\Services;

use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use SCMS\Finance\Enums\AccountType;
use SCMS\Finance\Enums\EntryStatus;
use SCMS\Finance\Enums\LineComponent;
use SCMS\Finance\Models\Account;
use SCMS\Finance\Support\Money;

/**
 * Every balance comes from here, and here only from the lines of posted entries (a reversed
 * entry and its reversal both count, so they cancel out). Cached balances on savings accounts
 * and loans are checked against these numbers, never the other way round.
 */
class LedgerService
{
    /**
     * Lines of posted entries, joined to their entry as "e".
     *
     * @param  list<int>|null  $branchIds  null = every branch
     */
    public function lines(?array $branchIds = null, DateTimeInterface|string|null $from = null, DateTimeInterface|string|null $to = null): Builder
    {
        return DB::table('finance_journal_lines as l')
            ->join('finance_journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->whereIn('e.status', [EntryStatus::Posted->value, EntryStatus::Reversed->value])
            ->when($branchIds !== null, fn ($q) => $q->whereIn('e.branch_id', $branchIds ?: [0]))
            ->when($from, fn ($q) => $q->where('e.entry_date', '>=', Carbon::parse($from)->toDateString()))
            ->when($to, fn ($q) => $q->where('e.entry_date', '<=', Carbon::parse($to)->toDateString()));
    }

    /** Debit minus credit of the lines. */
    public function net(Builder $lines): Money
    {
        $row = (clone $lines)->selectRaw('COALESCE(SUM(l.debit), 0) as debit, COALESCE(SUM(l.credit), 0) as credit')->first();

        return Money::of((string) $row->debit)->minus((string) $row->credit);
    }

    /** Money in a cash drawer or bank account. */
    public function cashBankBalance(int $cashBankAccountId, DateTimeInterface|string|null $asOf = null): Money
    {
        return $this->net($this->lines(null, null, $asOf)->where('l.cash_bank_account_id', $cashBankAccountId));
    }

    /** What the samiti owes the member on the savings account (credit balance of the liability). */
    public function savingsBalance(int $savingsAccountId, DateTimeInterface|string|null $asOf = null): Money
    {
        return $this->net($this->lines(null, null, $asOf)->where('l.savings_account_id', $savingsAccountId))->negated();
    }

    /** Principal the member still owes on the loan (debit balance of the receivable). */
    public function loanOutstanding(int $loanId, DateTimeInterface|string|null $asOf = null): Money
    {
        return $this->net($this->lines(null, null, $asOf)->where('l.loan_id', $loanId)->where('l.component', LineComponent::Principal->value));
    }

    /**
     * Balance of an account and every account below it, signed by its normal side
     * (assets and expenses positive when debit, the others when credit).
     *
     * @param  list<int>|null  $branchIds
     */
    public function accountBalance(Account $account, ?array $branchIds = null, DateTimeInterface|string|null $asOf = null, DateTimeInterface|string|null $from = null): Money
    {
        $net = $this->net($this->lines($branchIds, $from, $asOf)->whereIn('l.account_id', $this->descendantIds($account)));

        return $account->type->isDebitNormal() ? $net : $net->negated();
    }

    /**
     * Debit and credit per account, as rows [account_id => ['debit' => Money, 'credit' => Money]].
     *
     * @param  list<int>|null  $branchIds
     * @return Collection<int, array{debit: Money, credit: Money}>
     */
    public function totalsByAccount(?array $branchIds = null, DateTimeInterface|string|null $from = null, DateTimeInterface|string|null $to = null, ?callable $scope = null): Collection
    {
        $query = $this->lines($branchIds, $from, $to)
            ->groupBy('l.account_id')
            ->selectRaw('l.account_id, SUM(l.debit) as debit, SUM(l.credit) as credit');
        if ($scope) {
            $scope($query);
        }

        return $query->get()->mapWithKeys(fn ($row) => [(int) $row->account_id => [
            'debit' => Money::of((string) $row->debit),
            'credit' => Money::of((string) $row->credit),
        ]]);
    }

    /**
     * Trial balance as of a date: the debit or credit balance of every account with postings.
     *
     * @param  list<int>|null  $branchIds
     * @return array{rows: list<array{account: Account, debit: Money, credit: Money}>, debit: Money, credit: Money}
     */
    public function trialBalance(?array $branchIds, DateTimeInterface|string $asOf): array
    {
        $totals = $this->totalsByAccount($branchIds, null, $asOf);
        $accounts = Account::query()->whereKey($totals->keys()->all())->orderBy('code')->get();

        $rows = [];
        $debit = Money::zero();
        $credit = Money::zero();
        foreach ($accounts as $account) {
            $net = $totals[$account->id]['debit']->minus($totals[$account->id]['credit']);
            if ($net->isZero()) {
                continue;
            }
            $row = ['account' => $account, 'debit' => $net->isPositive() ? $net : Money::zero(), 'credit' => $net->isNegative() ? $net->negated() : Money::zero()];
            $debit = $debit->plus($row['debit']);
            $credit = $credit->plus($row['credit']);
            $rows[] = $row;
        }

        return ['rows' => $rows, 'debit' => $debit, 'credit' => $credit];
    }

    /**
     * Account ledger: balance brought forward, each posted line in date order with the running
     * balance (signed by the account's normal side), and the closing balance.
     *
     * @param  list<int>|null  $branchIds
     * @return array{opening: Money, rows: list<object>, closing: Money, debit: Money, credit: Money}
     */
    public function ledger(Account $account, ?array $branchIds, DateTimeInterface|string $from, DateTimeInterface|string $to, ?int $cashBankAccountId = null): array
    {
        $ids = $this->descendantIds($account);
        $sign = fn (Money $net) => $account->type->isDebitNormal() ? $net : $net->negated();
        $narrow = fn (Builder $q) => $q->whereIn('l.account_id', $ids)->when($cashBankAccountId, fn ($q) => $q->where('l.cash_bank_account_id', $cashBankAccountId));

        $opening = $sign($this->net($narrow($this->lines($branchIds, null, Carbon::parse($from)->subDay()))));

        $rows = $narrow($this->lines($branchIds, $from, $to))
            ->leftJoin('finance_accounts as a', 'a.id', '=', 'l.account_id')
            ->orderBy('e.entry_date')->orderBy('e.id')->orderBy('l.line_no')
            ->get(['l.*', 'e.number', 'e.entry_date', 'e.description as entry_description', 'e.source_type', 'e.source_id', 'e.status as entry_status', 'e.reversal_of_id', 'e.branch_id', 'a.code as account_code', 'a.name as account_name']);

        $running = $opening;
        $debit = Money::zero();
        $credit = Money::zero();
        foreach ($rows as $row) {
            $debit = $debit->plus((string) $row->debit);
            $credit = $credit->plus((string) $row->credit);
            $running = $running->plus($sign(Money::of((string) $row->debit)->minus((string) $row->credit)));
            $row->balance = $running;
        }

        return ['opening' => $opening, 'rows' => $rows->all(), 'closing' => $running, 'debit' => $debit, 'credit' => $credit];
    }

    /**
     * Balances of several sub-ledger ids at once (one query): savings accounts, loans or cash accounts.
     *
     * @param  'savings_account_id'|'loan_id'|'cash_bank_account_id'  $column
     * @param  list<int>  $ids
     * @return array<int, Money> debit minus credit per id
     */
    public function netBy(string $column, array $ids, DateTimeInterface|string|null $asOf = null, ?string $component = null): array
    {
        if (! in_array($column, ['savings_account_id', 'loan_id', 'cash_bank_account_id', 'member_id'], true) || $ids === []) {
            return [];
        }

        return $this->lines(null, null, $asOf)
            ->whereIn('l.'.$column, $ids)
            ->when($component, fn ($q) => $q->where('l.component', $component))
            ->groupBy('l.'.$column)
            ->selectRaw("l.{$column} as id, SUM(l.debit) - SUM(l.credit) as net")
            ->pluck('net', 'id')
            ->map(fn ($net) => Money::of((string) $net))
            ->all();
    }

    /**
     * The account and every account below it.
     *
     * @return list<int>
     */
    public function descendantIds(Account $account): array
    {
        $ids = [(int) $account->id];
        $frontier = [(int) $account->id];
        while ($frontier) {
            $frontier = Account::query()->whereIn('parent_id', $frontier)->pluck('id')->map(fn ($id) => (int) $id)->all();
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }

    /**
     * Totals per account type (income, expense, …) for a period, optionally leaving out entries
     * of some sources (the year-end closing entry).
     *
     * @param  list<int>|null  $branchIds
     * @param  list<string>  $exceptSources
     * @return array<string, Money> type => balance on its normal side
     */
    public function totalsByType(?array $branchIds, DateTimeInterface|string|null $from, DateTimeInterface|string|null $to, array $exceptSources = []): array
    {
        $rows = $this->lines($branchIds, $from, $to)
            ->join('finance_accounts as a', 'a.id', '=', 'l.account_id')
            ->when($exceptSources, fn ($q) => $q->whereNotIn('e.source_type', $exceptSources))
            ->groupBy('a.type')
            ->selectRaw('a.type, SUM(l.debit) as debit, SUM(l.credit) as credit')
            ->get();

        $totals = [];
        foreach (AccountType::cases() as $type) {
            $totals[$type->value] = Money::zero();
        }
        foreach ($rows as $row) {
            $type = AccountType::from($row->type);
            $net = Money::of((string) $row->debit)->minus((string) $row->credit);
            $totals[$type->value] = $type->isDebitNormal() ? $net : $net->negated();
        }

        return $totals;
    }
}
