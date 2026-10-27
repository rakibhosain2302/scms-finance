<?php

namespace SCMS\Finance\Http\Requests;

use SCMS\Finance\Support\Abilities;

class SavingsAccountRequest extends FinanceRequest
{
    protected function ability(): ?string
    {
        return Abilities::SAVINGS_MANAGE;
    }

    public function rules(): array
    {
        return [
            'branch_id' => $this->branchRule(),
            'member_id' => ['required', 'integer'],
            'savings_scheme_id' => ['required', 'integer', 'exists:finance_savings_schemes,id'],
            'opened_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:2000'],
            'opening_balance' => $this->user()?->can(Abilities::SAVINGS_OPENING) ? $this->moneyRule(false, false) : ['prohibited'],
        ];
    }
}
