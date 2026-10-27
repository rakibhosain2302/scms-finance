<?php

namespace SCMS\Finance\Http\Controllers;

use SCMS\Finance\Enums\AccountSubtype;
use SCMS\Finance\Enums\AccountType;
use SCMS\Finance\Enums\Frequency;
use SCMS\Finance\Enums\InterestMethod;
use SCMS\Finance\Http\Requests\LoanProductRequest;
use SCMS\Finance\Models\Account;
use SCMS\Finance\Models\LoanProduct;
use SCMS\Finance\Support\Abilities;
use SCMS\Finance\Support\AccountMap;

class LoanProductController extends Controller
{
    /** Frozen once a loan uses the product (loans copy the terms, the accounts hold their balances). */
    private const LOCKED = ['receivable_account_id', 'interest_income_account_id', 'penalty_income_account_id', 'fee_income_account_id'];

    public function index()
    {
        $this->authorizeAbility(Abilities::LOAN_PRODUCT_MANAGE);

        return $this->view('loans.products.index', ['products' => LoanProduct::query()->withCount('loans')->orderBy('name')->get()]);
    }

    public function create(AccountMap $map)
    {
        $this->authorizeAbility(Abilities::LOAN_PRODUCT_MANAGE);

        return $this->view('loans.products.form', $this->formData(new LoanProduct([
            'interest_method' => InterestMethod::Flat, 'interest_rate' => '0', 'repayment_frequency' => Frequency::Monthly,
            'min_installments' => 1, 'max_installments' => 12, 'min_amount' => '0', 'processing_fee_rate' => '0', 'grace_days' => 0, 'is_active' => true,
            'receivable_account_id' => $map->find('loan_receivable')?->id,
            'interest_income_account_id' => $map->find('interest_income')?->id,
            'penalty_income_account_id' => $map->find('penalty_income')?->id,
            'fee_income_account_id' => $map->find('fee_income')?->id,
        ])));
    }

    public function store(LoanProductRequest $request)
    {
        $this->authorizeAbility(Abilities::LOAN_PRODUCT_MANAGE);
        $product = LoanProduct::query()->create($request->validated());

        return redirect()->route('finance.loan-products.index')->with('success', __('Loan product :name added.', ['name' => $product->name]));
    }

    public function edit(LoanProduct $loanProduct)
    {
        $this->authorizeAbility(Abilities::LOAN_PRODUCT_MANAGE);

        return $this->view('loans.products.form', $this->formData($loanProduct));
    }

    public function update(LoanProductRequest $request, LoanProduct $loanProduct)
    {
        $this->authorizeAbility(Abilities::LOAN_PRODUCT_MANAGE);
        $data = $request->validated();
        if ($loanProduct->loans()->exists()) {
            foreach (self::LOCKED as $field) {
                if ((int) $data[$field] !== (int) $loanProduct->{$field}) {
                    return back()->withInput()->withErrors([$field => __('Loans use this product; its accounts cannot change.')]);
                }
            }
        }
        $loanProduct->update($data);

        return redirect()->route('finance.loan-products.index')->with('success', __('Loan product :name saved.', ['name' => $loanProduct->name]));
    }

    private function formData(LoanProduct $product): array
    {
        return [
            'product' => $product,
            'methods' => InterestMethod::options(),
            'frequencies' => Frequency::options(Frequency::repaymentCases()),
            'receivables' => Account::options(null, null, AccountSubtype::LoanReceivable),
            'incomeAccounts' => Account::options(null, AccountType::Income),
            'locked' => $product->exists && $product->loans()->exists(),
        ];
    }
}
