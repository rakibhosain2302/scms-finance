<?php

namespace SCMS\Finance\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Nit\Master\Support\Approvals;
use SCMS\Finance\Enums\EntryStatus;
use SCMS\Finance\Enums\SavingsAccountStatus;
use SCMS\Finance\Enums\SavingsTransactionType;
use SCMS\Finance\Http\Requests\ReasonRequest;
use SCMS\Finance\Http\Requests\SavingsTransactionRequest;
use SCMS\Finance\Models\CashBankAccount;
use SCMS\Finance\Models\SavingsAccount;
use SCMS\Finance\Models\SavingsTransaction;
use SCMS\Finance\Services\LedgerService;
use SCMS\Finance\Services\SavingsTransactionService;
use SCMS\Finance\Support\Abilities;
use SCMS\Finance\Support\Money;

class SavingsTransactionController extends Controller
{
    public function __construct(private readonly SavingsTransactionService $service)
    {
    }

    public function index(Request $request)
    {
        $this->authorizeAbility(Abilities::SAVINGS_VIEW);

        $transactions = SavingsTransaction::query()->with(['savingsAccount:id,account_number,member_id', 'cashBankAccount:id,name', 'branch:id,name'])
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('transaction_date', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('transaction_date', '<=', $d))
            ->when($request->query('q'), fn ($q, $term) => $q->where('number', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->orderByDesc('transaction_date')->orderByDesc('id')->paginate($this->perPage())->withQueryString();

        return $this->view('savings.transactions.index', [
            'transactions' => $transactions,
            'members' => $this->members()->labels($transactions->pluck('savingsAccount.member_id')),
            'types' => SavingsTransactionType::options(),
            'statuses' => EntryStatus::options(),
        ]);
    }

    public function create(Request $request, LedgerService $ledger)
    {
        $type = $request->query('type') === SavingsTransactionType::Withdrawal->value ? SavingsTransactionType::Withdrawal : SavingsTransactionType::Deposit;
        $this->authorizeAbility($type === SavingsTransactionType::Deposit ? Abilities::SAVINGS_DEPOSIT : Abilities::SAVINGS_WITHDRAW);

        $account = $request->query('account') ? SavingsAccount::query()->with('scheme')->find($request->query('account')) : null;
        $accounts = $account ? collect([$account]) : SavingsAccount::query()->where('status', SavingsAccountStatus::Active->value)
            ->when($request->query('q'), fn ($q, $term) => $q->where('account_number', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->orderBy('account_number')->limit(500)->get(['id', 'account_number', 'member_id', 'branch_id']);
        $members = $this->members()->labels($accounts->pluck('member_id'));

        return $this->view('savings.transactions.create', [
            'type' => $type,
            'account' => $account,
            'balance' => $account ? $ledger->savingsBalance($account->id) : null,
            'accounts' => $accounts->mapWithKeys(fn ($a) => [$a->id => $a->account_number.' · '.($members[$a->member_id] ?? '#'.$a->member_id)])->all(),
            'cashAccounts' => CashBankAccount::options($account?->branch_id ?? $this->defaultBranch()),
        ]);
    }

    public function store(SavingsTransactionRequest $request)
    {
        $data = $request->validated();
        $type = SavingsTransactionType::from($data['type']);
        $account = SavingsAccount::query()->findOrFail($data['savings_account_id']);
        $this->authorizeAbility($type === SavingsTransactionType::Deposit ? 'deposit' : 'withdraw', $account);

        if ($type === SavingsTransactionType::Withdrawal && $this->needsApproval($data['amount'])) {
            return $this->attempt(function () use ($account, $data, $request) {
                // Checked now (balance, limits), then carried out when someone else approves.
                DB::beginTransaction();
                try {
                    $this->service->withdraw($account, $data, $request->user());
                } finally {
                    DB::rollBack();
                }

                return Approvals::request('finance_withdrawal', 'post', SavingsTransaction::class, $data + ['savings_account_id' => $account->id], null,
                    __('Withdrawal of :amount from :account', ['amount' => Money::of($data['amount'])->format(), 'account' => $account->account_number]));
            }, fn () => redirect()->route('finance.savings-accounts.show', $account)->with('success', __('The withdrawal is above the limit and was sent for approval.')));
        }

        return $this->attempt(
            fn () => $type === SavingsTransactionType::Deposit
                ? $this->service->deposit($account, $data, $request->user())
                : $this->service->withdraw($account, $data, $request->user()),
            fn (SavingsTransaction $t) => redirect()->route('finance.savings-transactions.show', $t)
                ->with('success', __(':type :number posted.', ['type' => $t->type->label(), 'number' => $t->number])),
        );
    }

    private function needsApproval(string $amount): bool
    {
        $limit = config('scms-finance.rules.withdrawal_approval_above');

        return $limit !== null && $limit !== '' && class_exists(Approvals::class)
            && Money::of($amount)->greaterThan((string) $limit) && Approvals::needed('finance_withdrawal');
    }

    public function show(SavingsTransaction $savingsTransaction)
    {
        $this->authorizeAbility('view', $savingsTransaction);
        $savingsTransaction->load(['savingsAccount.scheme', 'cashBankAccount', 'journalEntry.lines.account', 'reversalEntry', 'creator', 'branch']);

        return $this->view('savings.transactions.show', ['transaction' => $savingsTransaction, 'member' => $savingsTransaction->savingsAccount->memberLabel()]);
    }

    public function reverse(ReasonRequest $request, SavingsTransaction $savingsTransaction)
    {
        $this->authorizeAbility('reverse', $savingsTransaction);

        return $this->attempt(
            fn () => $this->service->reverse($savingsTransaction, $request->validated('reason'), $request->user()),
            fn (SavingsTransaction $t) => redirect()->route('finance.savings-transactions.show', $t)->with('success', __(':number reversed.', ['number' => $t->number])),
        );
    }
}
