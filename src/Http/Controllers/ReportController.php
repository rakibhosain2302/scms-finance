<?php

namespace SCMS\Finance\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use SCMS\Finance\Enums\CashBankKind;
use SCMS\Finance\Exceptions\BranchAccessException;
use SCMS\Finance\Models\Account;
use SCMS\Finance\Models\CashBankAccount;
use SCMS\Finance\Services\LedgerService;
use SCMS\Finance\Services\ReportService;
use SCMS\Finance\Support\Abilities;
use SCMS\Finance\Support\FinanceModels;

/**
 * Read-only reports over posted entries. Filters: branch (only branches the user may report on),
 * period or date, and per report an account, cash account, kind or grouping.
 */
class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports, private readonly LedgerService $ledger)
    {
    }

    public function index()
    {
        $this->authorizeAbility(Abilities::REPORT_VIEW);

        return $this->view('reports.index');
    }

    /** Daily, monthly or yearly collections, withdrawals, loans, income and expense. */
    public function activity(Request $request)
    {
        [$branchIds, $filters] = $this->filters($request, today()->startOfMonth());
        $group = in_array($request->query('group'), ['day', 'month', 'branch'], true) ? $request->query('group') : 'day';

        $rows = match ($group) {
            'month' => $this->reports->activityByMonth($branchIds, $filters['from'], $filters['to']),
            'branch' => $this->reports->activityByBranch($branchIds, $filters['from'], $filters['to']),
            default => $this->reports->activityByDay($branchIds, $filters['from'], $filters['to']),
        };

        return $this->view('reports.activity', $filters + [
            'group' => $group,
            'rows' => $rows,
            'totals' => $this->reports->activity($branchIds, $filters['from'], $filters['to']),
            'branchNames' => FinanceModels::branch()::query()->pluck('name', 'id')->all(),
        ]);
    }

    public function cashbook(Request $request)
    {
        [$branchIds, $filters] = $this->filters($request, today());
        $kind = CashBankKind::tryFrom((string) $request->query('kind'));
        $cashId = $request->integer('cash_bank_account_id') ?: null;

        return $this->view('reports.cashbook', $filters + [
            'kind' => $kind,
            'cashId' => $cashId,
            'cashAccounts' => CashBankAccount::query()->withoutBranchScope()->forBranches($branchIds)->orderBy('name')->pluck('name', 'id')->all(),
            'book' => $this->reports->cashbook($branchIds, $filters['from'], $filters['to'], $kind, $cashId),
        ]);
    }

    public function ledger(Request $request)
    {
        [$branchIds, $filters] = $this->filters($request, today()->startOfMonth());
        $account = $request->integer('account_id') ? Account::query()->find($request->integer('account_id')) : null;

        return $this->view('reports.ledger', $filters + [
            'account' => $account,
            'accounts' => Account::query()->orderBy('code')->get()->mapWithKeys(fn ($a) => [$a->id => $a->label()])->all(),
            'ledger' => $account ? $this->ledger->ledger($account, $branchIds, $filters['from'], $filters['to']) : null,
        ]);
    }

    public function trialBalance(Request $request)
    {
        [$branchIds, $filters] = $this->filters($request, null);

        return $this->view('reports.trial-balance', $filters + ['report' => $this->ledger->trialBalance($branchIds, $filters['to'])]);
    }

    public function incomeStatement(Request $request)
    {
        [$branchIds, $filters] = $this->filters($request, $this->yearStart());

        return $this->view('reports.income-statement', $filters + ['report' => $this->reports->incomeStatement($branchIds, $filters['from'], $filters['to'])]);
    }

    public function balanceSheet(Request $request)
    {
        [$branchIds, $filters] = $this->filters($request, null);

        return $this->view('reports.balance-sheet', $filters + ['report' => $this->reports->balanceSheet($branchIds, $filters['to'])]);
    }

    public function loans(Request $request)
    {
        [$branchIds, $filters] = $this->filters($request, null);
        $report = $this->reports->loanPortfolio($branchIds, $filters['to'], $request->boolean('overdue'));

        return $this->view('reports.loans', $filters + [
            'report' => $report,
            'overdueOnly' => $request->boolean('overdue'),
            'members' => $this->members()->labels($report['loans']->pluck('member_id')),
            'period' => $this->reports->activity($branchIds, $filters['to']->copy()->startOfYear(), $filters['to']),
        ]);
    }

    public function savings(Request $request)
    {
        [$branchIds, $filters] = $this->filters($request, null);

        return $this->view('reports.savings', $filters + [
            'report' => $this->reports->savingsSummary($branchIds, $filters['to']),
            'period' => $this->reports->activity($branchIds, $filters['to']->copy()->startOfYear(), $filters['to']),
        ]);
    }

    public function branches(Request $request)
    {
        [$branchIds, $filters] = $this->filters($request, $this->yearStart());

        return $this->view('reports.branches', $filters + [
            'activity' => $this->reports->activityByBranch($branchIds, $filters['from'], $filters['to']),
            'balances' => $this->reports->balancesByBranch($branchIds, $filters['to']),
            'branchNames' => FinanceModels::branch()::query()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    /** @return array{0: list<int>|null, 1: array<string, mixed>} */
    private function filters(Request $request, ?Carbon $defaultFrom): array
    {
        $this->authorizeAbility(Abilities::REPORT_VIEW);
        $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        try {
            $branchIds = $this->reports->branchScope($request->user(), $request->query('branch_id'));
        } catch (BranchAccessException $e) {
            abort(403, $e->getMessage());
        }

        $to = $request->date('to') ?? today();
        $from = $defaultFrom ? ($request->date('from') ?? $defaultFrom) : null;
        if ($from && $from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$branchIds, [
            'from' => $from,
            'to' => $to,
            'branchId' => $request->query('branch_id'),
            'branchOptions' => $this->reports->branchOptions($request->user()),
            'branchLabel' => $branchIds === null ? __('All branches') : implode(', ', FinanceModels::branch()::query()->whereKey($branchIds)->pluck('name')->all()),
        ]];
    }

    private function yearStart(): Carbon
    {
        $year = app(\SCMS\Finance\Contracts\FinancialYearResolver::class)->current();

        return $year ? Carbon::parse($year->starts_on) : today()->startOfYear();
    }
}
