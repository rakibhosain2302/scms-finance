<?php

namespace SCMS\Finance\Http\Requests;

class TransferRequest extends FinanceRequest
{
    public function rules(): array
    {
        return array_merge($this->isMethod('post') ? ['branch_id' => $this->branchRule()] : [], [
            'transfer_date' => ['required', 'date', 'before_or_equal:today'],
            'from_account_id' => ['required', 'integer', 'exists:finance_cash_bank_accounts,id'],
            'to_account_id' => ['required', 'integer', 'exists:finance_cash_bank_accounts,id', 'different:from_account_id'],
            'amount' => $this->moneyRule(),
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'post' => ['boolean'],
        ]);
    }
}
