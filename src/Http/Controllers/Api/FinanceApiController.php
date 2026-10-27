<?php

namespace SCMS\Finance\Http\Controllers\Api;

use Illuminate\Http\Request;
use SCMS\Finance\Http\Controllers\Controller;
use SCMS\Finance\Http\Resources\AccountResource;
use SCMS\Finance\Http\Resources\CashBankAccountResource;
use SCMS\Finance\Http\Resources\LoanResource;
use SCMS\Finance\Http\Resources\SavingsAccountResource;
use SCMS\Finance\Models\Account;
use SCMS\Finance\Models\CashBankAccount;
use SCMS\Finance\Models\Loan;
use SCMS\Finance\Models\SavingsAccount;
use SCMS\Finance\Services\DashboardService;
use SCMS\Finance\Services\LedgerService;
use SCMS\Finance\Support\Abilities;

/** Read-only JSON; the same abilities and branch limits as the screens. Balances come from the ledger. */
class FinanceApiController extends Controller
{
    public function __construct(private readonly LedgerService $ledger)
    {
    }

    public function dashboard(Request $request, DashboardService $dashboard)
    {
        $this->authorizeAbility(Abilities::DASHBOARD);
        $summary = $dashboard->summary($request->user(), $request->query('branch_id'));

        return response()->json(['data' => collect($summary)->map(fn ($v) => is_array($v) ? array_map('strval', $v) : (is_object($v) ? (string) $v : $v))]);
    }

    public function accounts()
    {
        $this->authorizeAbility(Abilities::ACCOUNT_VIEW);

        return AccountResource::collection(Account::query()->orderBy('code')->get());
    }

    public function cashBank()
    {
        $this->authorizeAbility(Abilities::CASHBANK_VIEW);
        $accounts = CashBankAccount::query()->orderBy('name')->get();
        $balances = $this->ledger->netBy('cash_bank_account_id', $accounts->pluck('id')->all());
        $accounts->each(fn ($a) => $a->ledger_balance = $balances[$a->id] ?? null);

        return CashBankAccountResource::collection($accounts);
    }

    public function savingsAccounts()
    {
        $this->authorizeAbility(Abilities::SAVINGS_VIEW);
        $accounts = SavingsAccount::query()->with('scheme:id,code,name')->orderByDesc('id')->paginate($this->perPage());
        $balances = $this->ledger->netBy('savings_account_id', $accounts->pluck('id')->all());
        $accounts->each(fn ($a) => $a->ledger_balance = isset($balances[$a->id]) ? $balances[$a->id]->negated() : null);

        return SavingsAccountResource::collection($accounts);
    }

    public function savingsAccount(SavingsAccount $savingsAccount)
    {
        $this->authorizeAbility('view', $savingsAccount);
        $savingsAccount->ledger_balance = $this->ledger->savingsBalance($savingsAccount->id);

        return new SavingsAccountResource($savingsAccount->load('scheme:id,code,name'));
    }

    public function loans()
    {
        $this->authorizeAbility(Abilities::LOAN_VIEW);
        $loans = Loan::query()->with('product:id,code,name')->orderByDesc('id')->paginate($this->perPage());
        $outstanding = $this->ledger->netBy('loan_id', $loans->pluck('id')->all(), null, 'principal');
        $loans->each(fn ($l) => $l->ledger_outstanding = $outstanding[$l->id] ?? null);

        return LoanResource::collection($loans);
    }

    public function loan(Loan $loan)
    {
        $this->authorizeAbility('view', $loan);
        $loan->ledger_outstanding = $this->ledger->loanOutstanding($loan->id);

        return new LoanResource($loan->load(['product:id,code,name', 'installments']));
    }
}
