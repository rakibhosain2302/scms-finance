<?php

namespace SCMS\Finance\Http\Requests;

use Illuminate\Validation\Rule;
use SCMS\Finance\Enums\SavingsTransactionType;

class SavingsTransactionRequest extends FinanceRequest
{
    public function rules(): array
    {
        return [
            'savings_account_id' => ['required', 'integer', 'exists:finance_savings_accounts,id'],
            'type' => ['required', Rule::enum(SavingsTransactionType::class)],
            'amount' => $this->moneyRule(),
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'cash_bank_account_id' => ['required', 'integer', 'exists:finance_cash_bank_accounts,id'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
