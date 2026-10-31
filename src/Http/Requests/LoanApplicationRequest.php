<?php

namespace SCMS\Finance\Http\Requests;

use SCMS\Finance\Support\Abilities;

class LoanApplicationRequest extends FinanceRequest
{
    protected function ability(): ?string
    {
        return Abilities::LOAN_CREATE;
    }

    public function rules(): array
    {
        return [
            'branch_id' => $this->branchRule(),
            'member_id' => ['required', 'integer'],
            'loan_product_id' => ['required', 'integer', 'exists:finance_loan_products,id'],
            'principal' => $this->moneyRule(),
            'installments_count' => ['required', 'integer', 'min:1', 'max:1000'],
            'applied_on' => ['required', 'date', 'before_or_equal:today'],
            'purpose' => ['nullable', 'string', 'max:500'],
            'collateral' => ['nullable', 'string', 'max:2000'],
            'guarantors' => ['array', 'max:5'],
            'guarantors.*.name' => ['nullable', 'string', 'max:150'],
            'guarantors.*.relation' => ['nullable', 'string', 'max:50'],
            'guarantors.*.phone' => ['nullable', 'string', 'max:20'],
            'guarantors.*.nid' => ['nullable', 'string', 'max:20'],
        ];
    }
}
