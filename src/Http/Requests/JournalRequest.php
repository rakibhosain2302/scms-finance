<?php

namespace SCMS\Finance\Http\Requests;

use SCMS\Finance\Support\Money;

class JournalRequest extends FinanceRequest
{
    protected function prepareForValidation(): void
    {
        // Rows the form left empty are not lines.
        $this->merge(['lines' => array_values(array_filter((array) $this->input('lines', []),
            fn ($line) => is_array($line) && (filled($line['account_id'] ?? null) || filled($line['debit'] ?? null) || filled($line['credit'] ?? null))))]);
    }

    public function rules(): array
    {
        $scale = Money::scale();

        return array_merge($this->isMethod('post') ? ['branch_id' => $this->branchRule()] : [], [
            'entry_date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:100'],
            'lines' => ['required', 'array', 'min:2', 'max:100'],
            'lines.*.account_id' => ['required', 'integer', 'exists:finance_accounts,id'],
            'lines.*.debit' => ['nullable', "decimal:0,{$scale}", 'gte:0', 'max:9999999999999'],
            'lines.*.credit' => ['nullable', "decimal:0,{$scale}", 'gte:0', 'max:9999999999999'],
            'lines.*.description' => ['nullable', 'string', 'max:500'],
            'lines.*.cash_bank_account_id' => ['nullable', 'integer', 'exists:finance_cash_bank_accounts,id'],
            'post' => ['boolean'],
        ]);
    }
}
