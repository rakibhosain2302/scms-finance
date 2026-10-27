<?php

namespace SCMS\Finance\Http\Requests;

class SavingsAccountRequest extends FinanceRequest
{
    public function rules(): array
    {
        return [
            'branch_id' => $this->branchRule(),
            'member_id' => ['required', 'integer'],
            'savings_scheme_id' => ['required', 'integer', 'exists:finance_savings_schemes,id'],
            'opened_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
