<?php

namespace SCMS\Finance\Http\Requests;

class LoanRepaymentRequest extends FinanceRequest
{
    public function rules(): array
    {
        return [
            'amount' => $this->moneyRule(),
            'penalty_amount' => array_merge($this->moneyRule(false, false), ['lte:amount']),
            'repayment_date' => ['required', 'date', 'before_or_equal:today'],
            'cash_bank_account_id' => ['required', 'integer', 'exists:finance_cash_bank_accounts,id'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
