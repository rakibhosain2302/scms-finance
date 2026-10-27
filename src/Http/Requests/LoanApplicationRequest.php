<?php

namespace SCMS\Finance\Http\Requests;

class LoanApplicationRequest extends FinanceRequest
{
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
        ];
    }
}
