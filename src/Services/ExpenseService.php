<?php

namespace SCMS\Finance\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use SCMS\Finance\Enums\AccountType;
use SCMS\Finance\Enums\LineComponent;
use SCMS\Finance\Events\ExpensePosted;
use SCMS\Finance\Events\FinanceEvent;
use SCMS\Finance\Models\Account;
use SCMS\Finance\Models\CashBankAccount;
use SCMS\Finance\Models\Expense;
use SCMS\Finance\Models\Voucher;

/** Dr Expense account, Cr Cash/Bank (refused when the drawer or bank account would go below zero). */
class ExpenseService extends VoucherService
{
    protected function model(): string
    {
        return Expense::class;
    }

    protected function accountType(): AccountType
    {
        return AccountType::Expense;
    }

    protected function postedEvent(Voucher $voucher, ?Authenticatable $user): FinanceEvent
    {
        return new ExpensePosted($voucher, $user?->getAuthIdentifier());
    }

    protected function lines(Voucher $voucher, Account $account, CashBankAccount $cash): array
    {
        return [
            JournalService::debit($account, $voucher->amount, ['component' => LineComponent::Expense, 'description' => $voucher->payee]),
            JournalService::credit($cash->account, $voucher->amount, $this->cashLine($voucher, $cash)),
        ];
    }
}
