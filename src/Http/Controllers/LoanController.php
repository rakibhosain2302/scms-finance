<?php

namespace SCMS\Finance\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use SCMS\Finance\Enums\LoanStatus;
use SCMS\Finance\Exceptions\FinanceException;
use SCMS\Finance\Http\Requests\LoanApplicationRequest;
use SCMS\Finance\Http\Requests\LoanDisbursementRequest;
use SCMS\Finance\Http\Requests\ReasonRequest;
use SCMS\Finance\Models\CashBankAccount;
use SCMS\Finance\Models\Loan;
use SCMS\Finance\Models\LoanProduct;
use SCMS\Finance\Services\LedgerService;
use SCMS\Finance\Services\LoanService;
use SCMS\Finance\Support\Abilities;

class LoanController extends Controller
{
    public function __construct(private readonly LoanService $loans)
    {
    }

    public function index(Request $request)
    {
        $this->authorizeAbility(Abilities::LOAN_VIEW);

        $loans = Loan::query()->with(['product:id,name', 'branch:id,name'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('product'), fn ($q, $p) => $q->where('loan_product_id', $p))
            ->when($request->query('member'), fn ($q, $m) => $q->where('member_id', $m))
            ->when($request->query('q'), fn ($q, $term) => $q->where('loan_number', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->orderByDesc('id')->paginate($this->perPage())->withQueryString();

        return $this->view('loans.index', [
            'loans' => $loans,
            'members' => $this->members()->labels($loans->pluck('member_id')),
            'products' => LoanProduct::query()->orderBy('name')->pluck('name', 'id')->all(),
            'statuses' => LoanStatus::options(),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorizeAbility(Abilities::LOAN_CREATE);
        $branch = $this->formBranch();

        return $this->view('loans.create', [
            'branches' => $this->branchOptions(),
            'branch' => $branch,
            'memberSearch' => $request->query('member_q'),
            'members' => $this->members()->options($branch ? [$branch] : $this->visibleBranches(), $request->query('member_q')),
            'membersAvailable' => $this->members()->available(),
            'products' => LoanProduct::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function store(LoanApplicationRequest $request)
    {
        $this->authorizeAbility(Abilities::LOAN_CREATE);

        return $this->attempt(
            fn () => $this->loans->apply($request->validated(), $request->user()),
            fn (Loan $loan) => redirect()->route('finance.loans.show', $loan)->with('success', __('Application :number saved.', ['number' => $loan->loan_number])),
        );
    }

    /** JSON schedule for the application form (no database writes). */
    public function preview(Request $request)
    {
        $this->authorizeAbility(Abilities::LOAN_CREATE);
        $data = $request->validate([
            'loan_product_id' => ['required', 'integer', 'exists:finance_loan_products,id'],
            'principal' => ['required', 'decimal:0,2', 'gt:0'],
            'installments_count' => ['required', 'integer', 'min:1', 'max:1000'],
            'first_due_on' => ['nullable', 'date'],
        ]);
        $product = LoanProduct::query()->findOrFail($data['loan_product_id']);

        try {
            $schedule = $this->loans->preview($product, $data['principal'], (int) $data['installments_count'],
                isset($data['first_due_on']) ? Carbon::parse($data['first_due_on']) : $product->repayment_frequency->advance(today()));
        } catch (FinanceException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['installments' => array_map(fn ($row) => [
            'no' => $row['installment_no'],
            'due_date' => $row['due_date']->toDateString(),
            'principal' => $row['principal']->toString(),
            'interest' => $row['interest']->toString(),
            'total' => $row['principal']->plus($row['interest'])->toString(),
        ], $schedule)]);
    }

    public function show(Loan $loan, LedgerService $ledger)
    {
        $this->authorizeAbility('view', $loan);

        return $this->view('loans.show', [
            'loan' => $loan->load(['product', 'branch', 'creator', 'approver', 'installments', 'repayments.cashBankAccount', 'disbursementAccount', 'disbursementEntry']),
            'member' => $loan->memberLabel(),
            'outstanding' => $ledger->loanOutstanding($loan->id),
        ]);
    }

    public function approve(Request $request, Loan $loan)
    {
        $this->authorizeAbility('approve', $loan);
        $note = $request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null;

        return $this->attempt(fn () => $this->loans->approve($loan, $request->user(), $note), fn () => $this->back($loan, __('Loan approved.')));
    }

    public function reject(ReasonRequest $request, Loan $loan)
    {
        $this->authorizeAbility('approve', $loan);

        return $this->attempt(fn () => $this->loans->reject($loan, $request->user(), $request->validated('reason')), fn () => $this->back($loan, __('Loan rejected.')));
    }

    public function cancel(ReasonRequest $request, Loan $loan)
    {
        $this->authorizeAbility('cancel', $loan);

        return $this->attempt(fn () => $this->loans->cancel($loan, $request->user(), $request->validated('reason')), fn () => $this->back($loan, __('Loan cancelled.')));
    }

    public function disburseForm(Loan $loan)
    {
        $this->authorizeAbility('disburse', $loan);

        return $this->view('loans.disburse', [
            'loan' => $loan->load('product'),
            'member' => $loan->memberLabel(),
            'cashAccounts' => CashBankAccount::options($loan->branch_id),
            'firstDue' => $loan->repayment_frequency->advance(today()),
        ]);
    }

    public function disburse(LoanDisbursementRequest $request, Loan $loan)
    {
        $this->authorizeAbility('disburse', $loan);

        return $this->attempt(fn () => $this->loans->disburse($loan, $request->validated(), $request->user()), fn () => $this->back($loan, __('Loan disbursed.')));
    }

    public function reverseDisbursement(ReasonRequest $request, Loan $loan)
    {
        $this->authorizeAbility('reverseDisbursement', $loan);

        return $this->attempt(fn () => $this->loans->reverseDisbursement($loan, $request->validated('reason'), $request->user()), fn () => $this->back($loan, __('Disbursement reversed; the loan is cancelled.')));
    }

    private function back(Loan $loan, string $message)
    {
        return redirect()->route('finance.loans.show', $loan)->with('success', $message);
    }
}
