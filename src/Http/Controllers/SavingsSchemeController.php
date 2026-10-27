<?php

namespace SCMS\Finance\Http\Controllers;

use SCMS\Finance\Enums\AccountSubtype;
use SCMS\Finance\Enums\Frequency;
use SCMS\Finance\Http\Requests\SavingsSchemeRequest;
use SCMS\Finance\Models\Account;
use SCMS\Finance\Models\SavingsScheme;
use SCMS\Finance\Support\Abilities;
use SCMS\Finance\Support\AccountMap;

class SavingsSchemeController extends Controller
{
    public function index()
    {
        $this->authorizeAbility(Abilities::SAVINGS_MANAGE);

        return $this->view('savings.schemes.index', ['schemes' => SavingsScheme::query()->withCount('accounts')->with('liabilityAccount:id,code,name')->orderBy('name')->get()]);
    }

    public function create(AccountMap $map)
    {
        $this->authorizeAbility(Abilities::SAVINGS_MANAGE);

        return $this->view('savings.schemes.form', $this->formData(new SavingsScheme([
            'frequency' => Frequency::Flexible, 'min_deposit' => '0', 'minimum_balance' => '0', 'allow_withdrawal' => true, 'is_active' => true,
            'liability_account_id' => $map->find('member_savings')?->id,
        ])));
    }

    public function store(SavingsSchemeRequest $request)
    {
        $this->authorizeAbility(Abilities::SAVINGS_MANAGE);
        $scheme = SavingsScheme::query()->create($request->validated());

        return redirect()->route('finance.savings-schemes.index')->with('success', __('Scheme :name added.', ['name' => $scheme->name]));
    }

    public function edit(SavingsScheme $savingsScheme)
    {
        $this->authorizeAbility(Abilities::SAVINGS_MANAGE);

        return $this->view('savings.schemes.form', $this->formData($savingsScheme));
    }

    public function update(SavingsSchemeRequest $request, SavingsScheme $savingsScheme)
    {
        $this->authorizeAbility(Abilities::SAVINGS_MANAGE);
        $data = $request->validated();
        // Accounts already hold balances in the scheme's liability account: it stays.
        if ($savingsScheme->accounts()->exists() && (int) $data['liability_account_id'] !== (int) $savingsScheme->liability_account_id) {
            return back()->withInput()->withErrors(['liability_account_id' => __('Accounts already use this scheme; its liability account cannot change.')]);
        }
        $savingsScheme->update($data);

        return redirect()->route('finance.savings-schemes.index')->with('success', __('Scheme :name saved.', ['name' => $savingsScheme->name]));
    }

    private function formData(SavingsScheme $scheme): array
    {
        return [
            'scheme' => $scheme,
            'frequencies' => Frequency::options(),
            'liabilityAccounts' => Account::options(null, null, AccountSubtype::MemberSavings),
        ];
    }
}
