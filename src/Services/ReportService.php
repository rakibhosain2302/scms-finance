<?php

namespace SCMS\Finance\Services;

use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use SCMS\Finance\Contracts\BranchAccess;
use SCMS\Finance\Enums\AccountType;
use SCMS\Finance\Enums\CashBankKind;
use SCMS\Finance\Enums\InstallmentStatus;
use SCMS\Finance\Enums\LineComponent;
use SCMS\Finance\Enums\LoanStatus;
use SCMS\Finance\Exceptions\BranchAccessException;
use SCMS\Finance\Models\Account;
use SCMS\Finance\Models\Loan;
use SCMS\Finance\Models\SavingsScheme;
use SCMS\Finance\Support\Abilities;
use SCMS\Finance\Support\FinanceModels;
use SCMS\Finance\Support\Money;

/**
 * Finance reports. Every figure is a sum of posted journal lines (reversals cancel the entries
 * they reverse); the cached balances on savings accounts and loans are never read here.
 * Loan arrears come from the installment schedule, which is not a ledger figure.
 */
class ReportService
{
    /** Activity measures, in display order. */
    public const MEASURES = ['deposits', 'withdrawals', 'disbursed', 'principal_repaid', 'interest', 'penalty', 'fees', 'income', 'expense', 'cash_net'];

    public function __construct(
        private readonly LedgerService $ledger,
        private readonly BranchAccess $branches,
    ) {
    }

    /**
     * Branches a report covers: the one asked for (when the user may see it), else every branch
     * the user may report on. null = all branches.
     *
     * @return list<int>|null
     */
    public function branchScope(?Authenticatable $user, int|string|null $branchId = null): ?array
    {
        $all = $user === null || $user->can(Abilities::REPORT_ALL_BRANCHES);

        if ($branchId !== null && $branchId !== '') {
            if (! $all && ! $this->branches->canAccess($user, $branchId)) {
                throw new BranchAccessException();
            }

            return [(int) $branchId];
        }

        return $all ? null : $this->branches->visibleIds($user);
    }

    /**
     * Branches a user may pick on a report, id => name.
     *
     * @return array<int, string>
     */
    public function branchOptions(?Authenticatable $user): array
    {
        if ($user && $user->can(Abilities::REPORT_ALL_BRANCHES)) {
            return FinanceModels::branch()::query()->orderBy('name')->pluck('name', 'id')->all();
        }

        return $this->branches->options($user);
    }

    /**
     * Collections, payments, income and expense of a period.
     *
     * @param  list<int>|null  $branchIds
     * @return array<string, Money>
     */
    public function activity(?array $branchIds, DateTimeInterface|string $from, DateTimeInterface|string $to): array
    {
        $row = $this->activityQuery($branchIds, $from, $to)->first();

        return $this->measures($row);
    }

    /**
     * The same measures per day ("2026-07-01" => measures), days without postings left out.
     *
     * @param  list<int>|null  $branchIds
     * @return array<string, array<string, Money>>
     */
    public function activityByDay(?array $branchIds, DateTimeInterface|string $from, DateTimeInterface|string $to): array
    {
        return $this->activityQuery($branchIds, $from, $to)
            ->addSelect('e.entry_date')->groupBy('e.entry_date')->orderBy('e.entry_date')->get()
            ->mapWithKeys(fn ($row) => [Carbon::parse($row->entry_date)->toDateString() => $this->measures($row)])
            ->all();
    }

    /**
     * Per month ("2026-07" => measures), from the daily figures.
     *
     * @param  list<int>|null  $branchIds
     * @return array<string, array<string, Money>>
     */
    public function activityByMonth(?array $branchIds, DateTimeInterface|string $from, DateTimeInterface|string $to): array
    {
        $months = [];
        foreach ($this->activityByDay($branchIds, $from, $to) as $day => $measures) {
            $month = substr($day, 0, 7);
            foreach ($measures as $key => $amount) {
                $months[$month][$key] = ($months[$month][$key] ?? Money::zero())->plus($amount);
            }
        }

        return $months;
    }

    /**
     * Per branch (branch id => measures).
     *
     * @param  list<int>|null  $branchIds
     * @return array<int, array<string, Money>>
     */
    public function activityByBranch(?array $branchIds, DateTimeInterface|string $from, DateTimeInterface|string $to): array
    {
        return $this->activityQuery($branchIds, $from, $to)
            ->addSelect('e.branch_id')->groupBy('e.branch_id')->get()
            ->mapWithKeys(fn ($row) => [(int) $row->branch_id => $this->measures($row)])
            ->all();
    }

    /**
     * Cash book: balance brought forward, every receipt and payment of the cash (or bank) accounts
     * with the running balance, closing balance.
     *
     * @param  list<int>|null  $branchIds
     * @return array{opening: Money, rows: list<object>, receipts: Money, payments: Money, closing: Money}
     */
    public function cashbook(?array $branchIds, DateTimeInterface|string $from, DateTimeInterface|string $to, ?CashBankKind $kind = null, ?int $cashBankAccountId = null): array
    {
        $narrow = fn (Builder $q) => $q->join('finance_cash_bank_accounts as c', 'c.id', '=', 'l.cash_bank_account_id')
            ->when($kind, fn ($q) => $q->where('c.kind', $kind->value))
            ->when($cashBankAccountId, fn ($q) => $q->where('c.id', $cashBankAccountId));

        $opening = $this->ledger->net($narrow($this->ledger->lines($branchIds, null, Carbon::parse($from)->subDay())));
        $rows = $narrow($this->ledger->lines($branchIds, $from, $to))
            ->orderBy('e.entry_date')->orderBy('e.id')->orderBy('l.line_no')
            ->get(['l.debit', 'l.credit', 'l.description as line_description', 'e.id as entry_id', 'e.number', 'e.entry_date', 'e.description', 'e.source_type', 'e.reversal_of_id', 'c.name as cash_account']);

        $balance = $opening;
        $receipts = Money::zero();
        $payments = Money::zero();
        foreach ($rows as $row) {
            $receipts = $receipts->plus((string) $row->debit);
            $payments = $payments->plus((string) $row->credit);
            $balance = $balance->plus((string) $row->debit)->minus((string) $row->credit);
            $row->balance = $balance;
        }

        return ['opening' => $opening, 'rows' => $rows->all(), 'receipts' => $receipts, 'payments' => $payments, 'closing' => $balance];
    }

    /**
     * Income and expenditure (profit and loss) of a period, without the year-end closing entries.
     *
     * @param  list<int>|null  $branchIds
     * @return array{income: list<array{account: Account, amount: Money}>, expense: list<array{account: Account, amount: Money}>, total_income: Money, total_expense: Money, surplus: Money}
     */
    public function incomeStatement(?array $branchIds, DateTimeInterface|string $from, DateTimeInterface|string $to): array
    {
        $totals = $this->ledger->totalsByAccount($branchIds, $from, $to, fn ($q) => $q->where('e.source_type', '!=', 'year_close'));
        $groups = $this->byType($totals, [AccountType::Income, AccountType::Expense]);
        $income = Money::sum(array_column($groups['income'], 'amount'));
        $expense = Money::sum(array_column($groups['expense'], 'amount'));

        return ['income' => $groups['income'], 'expense' => $groups['expense'], 'total_income' => $income, 'total_expense' => $expense, 'surplus' => $income->minus($expense)];
    }

    /**
     * Balance sheet as of a date. Income and expense not yet closed into Accumulated surplus are
     * shown as one line, so assets = liabilities + equity always.
     *
     * @param  list<int>|null  $branchIds
     * @return array{asset: list<array{account: Account, amount: Money}>, liability: list<array{account: Account, amount: Money}>, equity: list<array{account: Account, amount: Money}>, unclosed: Money, total_asset: Money, total_liability: Money, total_equity: Money}
     */
    public function balanceSheet(?array $branchIds, DateTimeInterface|string $asOf): array
    {
        $totals = $this->ledger->totalsByAccount($branchIds, null, $asOf);
        $groups = $this->byType($totals, [AccountType::Asset, AccountType::Liability, AccountType::Equity, AccountType::Income, AccountType::Expense]);
        $unclosed = Money::sum(array_column($groups['income'], 'amount'))->minus(Money::sum(array_column($groups['expense'], 'amount')));

        return [
            'asset' => $groups['asset'],
            'liability' => $groups['liability'],
            'equity' => $groups['equity'],
            'unclosed' => $unclosed,
            'total_asset' => Money::sum(array_column($groups['asset'], 'amount')),
            'total_liability' => Money::sum(array_column($groups['liability'], 'amount')),
            'total_equity' => Money::sum(array_column($groups['equity'], 'amount'))->plus($unclosed),
        ];
    }

    /**
     * Running loans with their outstanding principal (ledger) and arrears (schedule).
     *
     * @param  list<int>|null  $branchIds
     * @return array{loans: Collection<int, Loan>, outstanding: Money, overdue: Money, overdue_loans: int}
     */
    public function loanPortfolio(?array $branchIds, ?DateTimeInterface $asOf = null, bool $overdueOnly = false): array
    {
        $asOf = Carbon::parse($asOf ?? today());
        $loans = Loan::query()->withoutBranchScope()->forBranches($branchIds)
            ->whereIn('status', LoanStatus::runningValues())
            ->with('product:id,name')
            ->orderBy('loan_number')->get();

        $outstanding = $this->ledger->netBy('loan_id', $loans->pluck('id')->all(), $asOf, LineComponent::Principal->value);
        $arrears = DB::table('finance_loan_installments')
            ->whereIn('loan_id', $loans->pluck('id')->all() ?: [0])
            ->where('status', '!=', InstallmentStatus::Paid->value)
            ->where('due_date', '<', $asOf->toDateString())
            ->groupBy('loan_id')
            ->selectRaw('loan_id, COUNT(*) as installments, SUM(principal_due - principal_paid + interest_due - interest_paid) as amount, MIN(due_date) as oldest')
            ->get()->keyBy('loan_id');

        foreach ($loans as $loan) {
            $loan->ledger_outstanding = $outstanding[$loan->id] ?? Money::zero();
            $loan->overdue_installments = (int) ($arrears[$loan->id]->installments ?? 0);
            $loan->overdue_amount = Money::of((string) ($arrears[$loan->id]->amount ?? '0'));
            $loan->overdue_since = $arrears[$loan->id]->oldest ?? null;
        }
        if ($overdueOnly) {
            $loans = $loans->filter(fn ($loan) => $loan->overdue_installments > 0)->values();
        }

        return [
            'loans' => $loans,
            'outstanding' => Money::sum($loans->pluck('ledger_outstanding')),
            'overdue' => Money::sum($loans->pluck('overdue_amount')),
            'overdue_loans' => $loans->filter(fn ($loan) => $loan->overdue_installments > 0)->count(),
        ];
    }

    /**
     * Savings per scheme: accounts and total balance (ledger) as of a date.
     *
     * @param  list<int>|null  $branchIds
     * @return array{rows: list<array{scheme: SavingsScheme, accounts: int, balance: Money}>, total: Money}
     */
    public function savingsSummary(?array $branchIds, DateTimeInterface|string|null $asOf = null): array
    {
        $balances = $this->ledger->lines($branchIds, null, $asOf)
            ->join('finance_savings_accounts as s', 's.id', '=', 'l.savings_account_id')
            ->groupBy('s.savings_scheme_id')
            ->selectRaw('s.savings_scheme_id, SUM(l.credit) - SUM(l.debit) as balance')
            ->pluck('balance', 'savings_scheme_id');
        $counts = DB::table('finance_savings_accounts')
            ->when($branchIds !== null, fn ($q) => $q->whereIn('branch_id', $branchIds ?: [0]))
            ->where('status', '!=', 'closed')
            ->groupBy('savings_scheme_id')->selectRaw('savings_scheme_id, COUNT(*) as n')->pluck('n', 'savings_scheme_id');

        $rows = [];
        foreach (SavingsScheme::query()->orderBy('name')->get() as $scheme) {
            $rows[] = ['scheme' => $scheme, 'accounts' => (int) ($counts[$scheme->id] ?? 0), 'balance' => Money::of((string) ($balances[$scheme->id] ?? '0'))];
        }

        return ['rows' => $rows, 'total' => Money::sum(array_column($rows, 'balance'))];
    }

    /**
     * Balances per branch as of a date: cash, bank, member savings, loans outstanding.
     *
     * @param  list<int>|null  $branchIds
     * @return array<int, array{cash: Money, bank: Money, savings: Money, loans: Money}>
     */
    public function balancesByBranch(?array $branchIds, DateTimeInterface|string|null $asOf = null): array
    {
        $rows = $this->ledger->lines($branchIds, null, $asOf)
            ->leftJoin('finance_cash_bank_accounts as c', 'c.id', '=', 'l.cash_bank_account_id')
            ->groupBy('e.branch_id')
            ->selectRaw('e.branch_id,
                SUM(CASE WHEN c.kind = ? THEN l.debit - l.credit ELSE 0 END) as cash,
                SUM(CASE WHEN c.kind = ? THEN l.debit - l.credit ELSE 0 END) as bank,
                SUM(CASE WHEN l.savings_account_id IS NOT NULL THEN l.credit - l.debit ELSE 0 END) as savings,
                SUM(CASE WHEN l.loan_id IS NOT NULL AND l.component = ? THEN l.debit - l.credit ELSE 0 END) as loans',
                [CashBankKind::Cash->value, CashBankKind::Bank->value, LineComponent::Principal->value])
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row->branch_id] = [
                'cash' => Money::of((string) $row->cash),
                'bank' => Money::of((string) $row->bank),
                'savings' => Money::of((string) $row->savings),
                'loans' => Money::of((string) $row->loans),
            ];
        }

        return $result;
    }

    /** One row of sums per measure; reversals carry the opposite sign and cancel out. */
    private function activityQuery(?array $branchIds, DateTimeInterface|string $from, DateTimeInterface|string $to): Builder
    {
        $c = fn (LineComponent $component) => DB::getPdo()->quote($component->value);

        return $this->ledger->lines($branchIds, $from, $to)
            ->join('finance_accounts as a', 'a.id', '=', 'l.account_id')
            ->selectRaw(implode(', ', [
                'COALESCE(SUM(CASE WHEN l.savings_account_id IS NOT NULL AND l.component = '.$c(LineComponent::Deposit).' THEN l.credit - l.debit ELSE 0 END), 0) as deposits',
                'COALESCE(SUM(CASE WHEN l.savings_account_id IS NOT NULL AND l.component = '.$c(LineComponent::Withdrawal).' THEN l.debit - l.credit ELSE 0 END), 0) as withdrawals',
                "COALESCE(SUM(CASE WHEN e.source_type = 'loan' AND l.component = ".$c(LineComponent::Principal).' THEN l.debit - l.credit ELSE 0 END), 0) as disbursed',
                "COALESCE(SUM(CASE WHEN e.source_type = 'loan_repayment' AND l.component = ".$c(LineComponent::Principal).' THEN l.credit - l.debit ELSE 0 END), 0) as principal_repaid',
                'COALESCE(SUM(CASE WHEN l.component = '.$c(LineComponent::Interest).' THEN l.credit - l.debit ELSE 0 END), 0) as interest',
                'COALESCE(SUM(CASE WHEN l.component = '.$c(LineComponent::Penalty).' THEN l.credit - l.debit ELSE 0 END), 0) as penalty',
                'COALESCE(SUM(CASE WHEN l.component = '.$c(LineComponent::Fee).' THEN l.credit - l.debit ELSE 0 END), 0) as fees',
                "COALESCE(SUM(CASE WHEN a.type = 'income' AND e.source_type <> 'year_close' THEN l.credit - l.debit ELSE 0 END), 0) as income",
                "COALESCE(SUM(CASE WHEN a.type = 'expense' AND e.source_type <> 'year_close' THEN l.debit - l.credit ELSE 0 END), 0) as expense",
                // Net change of all cash drawers and bank accounts (transfers between them cancel out).
                'COALESCE(SUM(CASE WHEN l.cash_bank_account_id IS NOT NULL THEN l.debit - l.credit ELSE 0 END), 0) as cash_net',
            ]));
    }

    /** @return array<string, Money> */
    private function measures(?object $row): array
    {
        $measures = [];
        foreach (self::MEASURES as $key) {
            $measures[$key] = Money::of($row ? (string) $row->{$key} : '0');
        }

        return $measures;
    }

    /**
     * @param  Collection<int, array{debit: Money, credit: Money}>  $totals
     * @param  list<AccountType>  $types
     * @return array<string, list<array{account: Account, amount: Money}>>
     */
    private function byType(Collection $totals, array $types): array
    {
        $groups = array_fill_keys(array_map(fn ($t) => $t->value, $types), []);
        $accounts = Account::query()->whereKey($totals->keys()->all())->ofType(...$types)->orderBy('code')->get();
        foreach ($accounts as $account) {
            $net = $totals[$account->id]['debit']->minus($totals[$account->id]['credit']);
            $amount = $account->type->isDebitNormal() ? $net : $net->negated();
            if (! $amount->isZero()) {
                $groups[$account->type->value][] = ['account' => $account, 'amount' => $amount];
            }
        }

        return $groups;
    }
}
