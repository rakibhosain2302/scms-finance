<?php

namespace SCMS\Finance\Http\Controllers;

use Illuminate\Http\Request;
use SCMS\Finance\Enums\EntryStatus;
use SCMS\Finance\Http\Requests\LoanRepaymentRequest;
use SCMS\Finance\Http\Requests\ReasonRequest;
use SCMS\Finance\Models\CashBankAccount;
use SCMS\Finance\Models\Loan;
use SCMS\Finance\Models\LoanInstallment;
use SCMS\Finance\Models\LoanRepayment;
use SCMS\Finance\Services\LedgerService;
use SCMS\Finance\Services\LoanRepaymentService;
use SCMS\Finance\Support\Abilities;
use SCMS\Finance\Support\Money;

class LoanRepaymentController extends Controller
{
    public function __construct(private readonly LoanRepaymentService $service)
    {
    }

    public function index(Request $request)
    {
        $this->authorizeAbility(Abilities::LOAN_VIEW);

        $repayments = LoanRepayment::query()->with(['loan:id,loan_number,member_id', 'cashBankAccount:id,name', 'branch:id,name'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('repayment_date', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('repayment_date', '<=', $d))
            ->when($request->query('q'), fn ($q, $term) => $q->where('number', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->orderByDesc('repayment_date')->orderByDesc('id')->paginate($this->perPage())->withQueryString();

        return $this->view('loans.repayments.index', [
            'repayments' => $repayments,
            'members' => $this->members()->labels($repayments->pluck('loan.member_id')),
            'statuses' => EntryStatus::options(),
        ]);
    }

    public function create(Loan $loan, LedgerService $ledger)
    {
        $this->authorizeAbility('repay', $loan);
        $unpaid = LoanInstallment::query()->where('loan_id', $loan->id)->unpaid()->orderBy('installment_no')->get();
        $due = $unpaid->filter(fn ($i) => $i->due_date->lte(today()));

        return $this->view('loans.repayments.create', [
            'loan' => $loan->load('product'),
            'member' => $loan->memberLabel(),
            'outstanding' => $ledger->loanOutstanding($loan->id),
            'payoff' => Money::sum($unpaid->map->remaining()),
            'dueNow' => Money::sum($due->map->remaining()),
            'next' => $unpaid->first(),
            'cashAccounts' => CashBankAccount::options($loan->branch_id),
        ]);
    }

    public function store(LoanRepaymentRequest $request, Loan $loan)
    {
        $this->authorizeAbility('repay', $loan);

        return $this->attempt(
            fn () => $this->service->repay($loan, $request->validated(), $request->user()),
            fn (LoanRepayment $r) => redirect()->route('finance.loan-repayments.show', $r)->with('success', __('Repayment :number posted.', ['number' => $r->number])),
        );
    }

    public function show(LoanRepayment $loanRepayment)
    {
        $this->authorizeAbility('view', $loanRepayment);
        $loanRepayment->load(['loan', 'allocations.installment', 'cashBankAccount', 'journalEntry.lines.account', 'reversalEntry', 'creator', 'branch']);

        return $this->view('loans.repayments.show', ['repayment' => $loanRepayment, 'member' => $loanRepayment->loan->memberLabel()]);
    }

    public function reverse(ReasonRequest $request, LoanRepayment $loanRepayment)
    {
        $this->authorizeAbility('reverse', $loanRepayment);

        return $this->attempt(
            fn () => $this->service->reverse($loanRepayment, $request->validated('reason'), $request->user()),
            fn (LoanRepayment $r) => redirect()->route('finance.loan-repayments.show', $r)->with('success', __(':number reversed.', ['number' => $r->number])),
        );
    }
}
