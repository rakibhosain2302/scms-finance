<?php

namespace SCMS\Finance\Http\Requests;

use SCMS\Finance\Support\Abilities;

class VoucherRequest extends FinanceRequest
{
    protected function ability(): ?string
    {
        return str_contains((string) $this->route()?->getName(), 'incomes') ? Abilities::INCOME_CREATE : Abilities::EXPENSE_CREATE;
    }

    public function rules(): array
    {
        $attachments = config('scms-finance.attachments');

        return array_merge($this->isMethod('post') ? ['branch_id' => $this->branchRule()] : [], [
            'voucher_date' => ['required', 'date', 'before_or_equal:today'],
            'account_id' => ['required', 'integer', 'exists:finance_accounts,id'],
            'cash_bank_account_id' => ['required', 'integer', 'exists:finance_cash_bank_accounts,id'],
            'amount' => $this->moneyRule(),
            'payer' => ['nullable', 'string', 'max:255'],
            'payee' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'attachment' => ['nullable', 'file', 'max:'.(int) ($attachments['max_kb'] ?? 4096), 'mimes:'.implode(',', (array) ($attachments['mimes'] ?? ['pdf']))],
            'post' => ['boolean'],
        ]);
    }
}
