<?php

namespace SCMS\Finance\Http\Requests;

use Illuminate\Validation\Rule;
use SCMS\Finance\Enums\AccountSubtype;
use SCMS\Finance\Enums\AccountType;
use SCMS\Finance\Enums\Frequency;
use SCMS\Finance\Enums\InterestMethod;

class LoanProductRequest extends FinanceRequest
{
    public function rules(): array
    {
        $account = fn (string $column, string $value) => ['required', 'integer', Rule::exists('finance_accounts', 'id')->where($column, $value)->where('is_postable', true)];

        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('finance_loan_products', 'code')->ignore($this->route('loanProduct')?->id)],
            'name' => ['required', 'string', 'max:255'],
            'interest_method' => ['required', Rule::enum(InterestMethod::class)],
            'interest_rate' => array_merge(['required_unless:interest_method,none'], array_slice($this->rateRule(), 1)),
            'repayment_frequency' => ['required', Rule::in(array_map(fn ($f) => $f->value, Frequency::repaymentCases()))],
            'min_installments' => ['required', 'integer', 'min:1', 'max:1000'],
            'max_installments' => ['required', 'integer', 'gte:min_installments', 'max:1000'],
            'min_amount' => $this->moneyRule(true, false),
            'max_amount' => array_merge($this->moneyRule(false), ['gte:min_amount']),
            'processing_fee_rate' => $this->rateRule(),
            'grace_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'receivable_account_id' => $account('subtype', AccountSubtype::LoanReceivable->value),
            'interest_income_account_id' => $account('type', AccountType::Income->value),
            'penalty_income_account_id' => $account('type', AccountType::Income->value),
            'fee_income_account_id' => $account('type', AccountType::Income->value),
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);
        if ($key === null) {
            $data['interest_rate'] = ($data['interest_method'] ?? null) === InterestMethod::None->value ? '0' : ($data['interest_rate'] ?? '0');
            $data['processing_fee_rate'] ??= '0';
            $data['grace_days'] ??= 0;
        }

        return $data;
    }
}
