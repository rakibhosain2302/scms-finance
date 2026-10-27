<?php

namespace SCMS\Finance\Http\Controllers;

use SCMS\Finance\Enums\AccountType;
use SCMS\Finance\Models\Income;
use SCMS\Finance\Services\IncomeService;
use SCMS\Finance\Services\VoucherService;

class IncomeController extends VoucherController
{
    public function __construct(private readonly IncomeService $incomes)
    {
    }

    protected function model(): string
    {
        return Income::class;
    }

    protected function service(): VoucherService
    {
        return $this->incomes;
    }

    protected function accountType(): AccountType
    {
        return AccountType::Income;
    }

    protected function routeName(): string
    {
        return 'incomes';
    }

    protected function labels(): array
    {
        return ['route' => 'incomes', 'kind' => 'income', 'title' => __('Income'), 'singular' => __('income voucher'), 'party' => 'payer', 'partyLabel' => __('Received from'), 'icon' => 'fas fa-arrow-down'];
    }
}
