<?php

namespace SCMS\Finance\Http\Controllers;

use Illuminate\Http\Request;
use SCMS\Finance\Enums\AccountSubtype;
use SCMS\Finance\Enums\CashBankKind;
use SCMS\Finance\Http\Requests\CashBankAccountRequest;
use SCMS\Finance\Models\Account;
use SCMS\Finance\Models\CashBankAccount;
use SCMS\Finance\Services\CashBankService;
use SCMS\Finance\Services\LedgerService;
use SCMS\Finance\Services\ReportService;
use SCMS\Finance\Support\Abilities;
use SCMS\Finance\Support\Money;

class CashBankAccountController extends Controller
{
    public function __construct(private readonly CashBankService $service)
    {
    }

    public function index(Request $request, LedgerService $ledger)
    {
        $this->authorizeAbility(Abilities::CASHBANK_VIEW);

        $accounts = CashBankAccount::query()->with(['branch:id,name', 'account:id,code,name'])
            ->when($request->query('kind'), fn ($q, $k) => $q->where('kind', $k))
            ->orderBy('branch_id')->orderBy('kind')->orderBy('name')->get();
        $balances = $ledger->netBy('cash_bank_account_id', $accounts->pluck('id')->all());

        return $this->view('cash-bank.index', [
            'accounts' => $accounts,
            'balances' => $balances,
            'totals' => [
                'cash' => Money::sum($accounts->where('kind', CashBankKind::Cash)->map(fn ($a) => $balances[$a->id] ?? null)),
                'bank' => Money::sum($accounts->where('kind', CashBankKind::Bank)->map(fn ($a) => $balances[$a->id] ?? null)),
            ],
        ]);
    }

    public function create()
    {
        $this->authorizeAbility(Abilities::CASHBANK_MANAGE);

        return $this->view('cash-bank.form', [
            'cash' => new CashBankAccount(['is_active' => true]),
            'branches' => $this->branchOptions(),
            'branch' => $this->defaultBranch(),
            'glAccounts' => Account::query()->postable()->ofSubtype(AccountSubtype::Cash, AccountSubtype::Bank)->orderBy('code')->get(),
        ]);
    }

    public function store(CashBankAccountRequest $request)
    {
        $this->authorizeAbility(Abilities::CASHBANK_MANAGE);
        $data = $request->validated();

        return $this->attempt(
            fn () => $this->service->create((int) $data['branch_id'], CashBankKind::from($data['kind']), (int) $data['account_id'], $data, $request->user(), $data['opening_balance'] ?? null, $data['opening_date'] ?? null),
            fn (CashBankAccount $c) => redirect()->route('finance.cash-bank.show', $c)->with('success', __(':name added.', ['name' => $c->name])),
        );
    }

    public function show(Request $request, CashBankAccount $cashBankAccount, ReportService $reports)
    {
        $this->authorizeAbility('view', $cashBankAccount);
        $from = $request->date('from') ?? today()->startOfMonth();
        $to = $request->date('to') ?? today();

        return $this->view('cash-bank.show', [
            'cash' => $cashBankAccount->load(['branch', 'account']),
            'balance' => $this->service->balance($cashBankAccount),
            'book' => $reports->cashbook(null, $from, $to, null, $cashBankAccount->id),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function edit(CashBankAccount $cashBankAccount)
    {
        $this->authorizeAbility('update', $cashBankAccount);

        return $this->view('cash-bank.form', ['cash' => $cashBankAccount, 'branches' => [], 'branch' => $cashBankAccount->branch_id, 'glAccounts' => collect()]);
    }

    public function update(CashBankAccountRequest $request, CashBankAccount $cashBankAccount)
    {
        $this->authorizeAbility('update', $cashBankAccount);

        return $this->attempt(
            fn () => $this->service->update($cashBankAccount, $request->validated(), $request->user()),
            fn (CashBankAccount $c) => redirect()->route('finance.cash-bank.show', $c)->with('success', __(':name saved.', ['name' => $c->name])),
        );
    }
}
