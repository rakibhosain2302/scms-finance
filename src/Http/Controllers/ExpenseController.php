<?php

namespace SCMS\Finance\Http\Controllers;

use SCMS\Finance\Enums\AccountType;
use SCMS\Finance\Models\Expense;
use SCMS\Finance\Services\ExpenseService;
use SCMS\Finance\Services\VoucherService;

class ExpenseController extends VoucherController
{
    public function __construct(private readonly ExpenseService $expenses)
    {
    }

    protected function model(): string
    {
        return Expense::class;
    }

    protected function service(): VoucherService
    {
        return $this->expenses;
    }

    protected function accountType(): AccountType
    {
        return AccountType::Expense;
    }

    protected function routeName(): string
    {
        return 'expenses';
    }

    protected function labels(): array
    {
        return ['route' => 'expenses', 'kind' => 'expense', 'title' => __('Expenses'), 'singular' => __('expense voucher'), 'party' => 'payee', 'partyLabel' => __('Paid to'), 'icon' => 'fas fa-arrow-up'];
    }
}
