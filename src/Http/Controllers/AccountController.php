<?php

namespace SCMS\Finance\Http\Controllers;

use SCMS\Finance\Enums\AccountSubtype;
use SCMS\Finance\Enums\AccountType;
use SCMS\Finance\Http\Requests\AccountRequest;
use SCMS\Finance\Models\Account;
use SCMS\Finance\Services\AccountService;
use SCMS\Finance\Services\LedgerService;
use SCMS\Finance\Support\Abilities;
use SCMS\Finance\Support\FinanceModels;
use SCMS\Finance\Support\Money;

class AccountController extends Controller
{
    public function __construct(private readonly AccountService $accounts)
    {
    }

    public function index(LedgerService $ledger)
    {
        $this->authorizeAbility(Abilities::ACCOUNT_VIEW);

        $accounts = Account::query()->orderBy('code')->get();
        $totals = $ledger->totalsByAccount($this->visibleBranches());

        // Balances on each account's normal side; group accounts add up their children.
        $balances = [];
        foreach ($accounts as $account) {
            $t = $totals[$account->id] ?? null;
            $net = $t ? $t['debit']->minus($t['credit']) : Money::zero();
            $balances[$account->id] = $account->type->isDebitNormal() ? $net : $net->negated();
        }
        $byParent = $accounts->groupBy('parent_id');
        $rollUp = function (Account $account) use (&$rollUp, &$balances, $byParent): Money {
            foreach ($byParent[$account->id] ?? [] as $child) {
                $balances[$account->id] = $balances[$account->id]->plus($rollUp($child));
            }

            return $balances[$account->id];
        };
        foreach ($byParent[''] ?? [] as $root) {
            $rollUp($root);
        }

        return $this->view('accounts.index', ['accounts' => $accounts, 'byParent' => $byParent, 'balances' => $balances]);
    }

    public function create()
    {
        $this->authorizeAbility(Abilities::ACCOUNT_MANAGE);

        return $this->view('accounts.form', $this->formData(new Account(['is_postable' => true, 'is_active' => true, 'subtype' => 'general', 'parent_id' => request('parent')])));
    }

    public function store(AccountRequest $request)
    {
        $this->authorizeAbility(Abilities::ACCOUNT_MANAGE);

        return $this->attempt(
            fn () => $this->accounts->create($request->validated(), $request->user()),
            fn (Account $a) => redirect()->route('finance.accounts.index')->with('success', __('Account :code added.', ['code' => $a->code])),
        );
    }

    public function show(Account $account)
    {
        return redirect()->route('finance.reports.ledger', ['account_id' => $account->id]);
    }

    public function edit(Account $account)
    {
        $this->authorizeAbility(Abilities::ACCOUNT_MANAGE);

        return $this->view('accounts.form', $this->formData($account) + ['used' => $this->accounts->hasPostings($account)]);
    }

    public function update(AccountRequest $request, Account $account)
    {
        $this->authorizeAbility(Abilities::ACCOUNT_MANAGE);

        return $this->attempt(
            fn () => $this->accounts->update($account, $request->validated()),
            fn () => redirect()->route('finance.accounts.index')->with('success', __('Account :code saved.', ['code' => $account->code])),
        );
    }

    public function destroy(Account $account)
    {
        $this->authorizeAbility(Abilities::ACCOUNT_MANAGE);

        return $this->attempt(
            fn () => $this->accounts->delete($account),
            fn () => redirect()->route('finance.accounts.index')->with('success', __('Account deleted.')),
        );
    }

    private function formData(Account $account): array
    {
        return [
            'account' => $account,
            'used' => false,
            'parents' => Account::query()->where('is_postable', false)->whereKeyNot($account->id)->orderBy('code')->get()
                ->mapWithKeys(fn ($a) => [$a->id => $a->label().' ('.$a->type->label().')'])->all(),
            'types' => AccountType::options(),
            'subtypes' => AccountSubtype::options(),
            'branches' => FinanceModels::branch()::query()->orderBy('name')->pluck('name', 'id')->all(),
        ];
    }
}
