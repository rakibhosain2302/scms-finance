<?php

namespace SCMS\Finance\Http\Requests;

use SCMS\Finance\Support\Abilities;

class LoanDisbursementRequest extends FinanceRequest
{
    protected function ability(): ?string
    {
        return Abilities::LOAN_DISBURSE;
    }

    public function rules(): array
    {
        return [
            'disbursed_on' => ['required', 'date', 'before_or_equal:today'],
            'first_due_on' => ['required', 'date', 'after:disbursed_on'],
            'cash_bank_account_id' => ['required', 'integer', 'exists:finance_cash_bank_accounts,id'],
        ];
    }
}
