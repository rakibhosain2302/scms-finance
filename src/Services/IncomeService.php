<?php

namespace SCMS\Finance\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use SCMS\Finance\Enums\AccountType;
use SCMS\Finance\Enums\LineComponent;
use SCMS\Finance\Events\FinanceEvent;
use SCMS\Finance\Events\IncomePosted;
use SCMS\Finance\Models\Account;
use SCMS\Finance\Models\CashBankAccount;
use SCMS\Finance\Models\Income;
use SCMS\Finance\Models\Voucher;

/** Dr Cash/Bank, Cr Income account. */
class IncomeService extends VoucherService
{
    protected function model(): string
    {
        return Income::class;
    }

    protected function accountType(): AccountType
    {
        return AccountType::Income;
    }

    protected function postedEvent(Voucher $voucher, ?Authenticatable $user): FinanceEvent
    {
        return new IncomePosted($voucher, $user?->getAuthIdentifier());
    }

    protected function lines(Voucher $voucher, Account $account, CashBankAccount $cash): array
    {
        return [
            JournalService::debit($cash->account, $voucher->amount, $this->cashLine($voucher, $cash)),
            JournalService::credit($account, $voucher->amount, ['component' => LineComponent::Income, 'description' => $voucher->payer]),
        ];
    }
}
