<?php

namespace SCMS\Finance\Http\Requests;

use Illuminate\Validation\Rule;
use SCMS\Finance\Enums\SavingsTransactionType;
use SCMS\Finance\Support\Abilities;

class SavingsTransactionRequest extends FinanceRequest
{
    protected function ability(): ?string
    {
        return $this->input('type') === 'withdrawal' ? Abilities::SAVINGS_WITHDRAW : Abilities::SAVINGS_DEPOSIT;
    }

    public function rules(): array
    {
        return [
            'savings_account_id' => ['required', 'integer', 'exists:finance_savings_accounts,id'],
            // Interest is credited by SavingsInterestService, never through this form.
            'type' => ['required', Rule::in([SavingsTransactionType::Deposit->value, SavingsTransactionType::Withdrawal->value])],
            'amount' => $this->moneyRule(),
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'cash_bank_account_id' => ['required', 'integer', 'exists:finance_cash_bank_accounts,id'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
