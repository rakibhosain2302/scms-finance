<?php

namespace SCMS\Finance\Http\Requests;

use Illuminate\Validation\Rule;
use SCMS\Finance\Enums\CashBankKind;

class CashBankAccountRequest extends FinanceRequest
{
    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return array_merge($creating ? [
            'branch_id' => $this->branchRule(),
            'kind' => ['required', Rule::enum(CashBankKind::class)],
            'account_id' => ['required', 'integer', 'exists:finance_accounts,id'],
            'opening_balance' => $this->moneyRule(false, false),
            'opening_date' => ['nullable', 'date', 'before_or_equal:today'],
        ] : [], [
            'name' => ['required', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_branch' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
