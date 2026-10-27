<?php

namespace SCMS\Finance\Http\Requests;

use Illuminate\Validation\Rule;
use SCMS\Finance\Enums\AccountSubtype;
use SCMS\Finance\Enums\Frequency;
use SCMS\Finance\Support\Abilities;

class SavingsSchemeRequest extends FinanceRequest
{
    protected function ability(): ?string
    {
        return Abilities::SAVINGS_MANAGE;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('finance_savings_schemes', 'code')->ignore($this->route('savingsScheme')?->id)],
            'name' => ['required', 'string', 'max:255'],
            'frequency' => ['required', Rule::enum(Frequency::class)],
            'min_deposit' => $this->moneyRule(true, false),
            'max_deposit' => array_merge($this->moneyRule(false), ['gte:min_deposit']),
            'minimum_balance' => $this->moneyRule(true, false),
            'allow_withdrawal' => ['boolean'],
            'max_withdrawals_per_month' => ['nullable', 'integer', 'min:1', 'max:100'],
            'interest_rate' => $this->rateRule(),
            'liability_account_id' => ['required', 'integer', Rule::exists('finance_accounts', 'id')
                ->where('subtype', AccountSubtype::MemberSavings->value)->where('is_postable', true)],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
