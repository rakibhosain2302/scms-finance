<?php

namespace SCMS\Finance\Tests\Feature;

use SCMS\Finance\Enums\EntryStatus;
use SCMS\Finance\Exceptions\FinanceException;
use SCMS\Finance\Exceptions\InsufficientBalanceException;
use SCMS\Finance\Exceptions\InvalidStateException;
use SCMS\Finance\Services\ExpenseService;
use SCMS\Finance\Services\IncomeService;
use SCMS\Finance\Tests\FinanceTestCase;

class IncomeExpenseTest extends FinanceTestCase
{
    private function data(string $code, string $amount, $cash = null): array
    {
        return ['branch_id' => $this->branchA->id, 'voucher_date' => today()->toDateString(), 'account_id' => $this->account($code)->id,
            'cash_bank_account_id' => ($cash ?? $this->cashA)->id, 'amount' => $amount, 'description' => 'Test'];
    }

    public function test_income_debits_cash_and_credits_the_income_account(): void
    {
        $income = app(IncomeService::class)->create($this->data('4500', '250') + ['payer' => 'New member'], null, true, $this->superAdmin());

        $this->assertSame(EntryStatus::Posted, $income->status);
        $this->assertSame(['1101' => ['250.00', '0.00'], '4500' => ['0.00', '250.00']], $this->lines($income->journal_entry_id));
        $this->assertSame('250.00', $this->balance('4500'));
        $this->assertMatchesRegularExpression('/^INC-\d{4}-\d{6}$/', $income->number);
    }

    public function test_expense_draft_is_edited_then_posted_as_debit_expense_credit_cash(): void
    {
        $service = app(ExpenseService::class);
        $expense = $service->create($this->data('5300', '3000'), null, false, $this->superAdmin());
        $this->assertSame(EntryStatus::Draft, $expense->status);
        $this->assertSame('100000.00', $this->cashBalance($this->cashA->id));

        $expense = $service->update($expense, ['amount' => '3500', 'voucher_date' => today()->toDateString(), 'account_id' => $this->account('5300')->id, 'cash_bank_account_id' => $this->cashA->id], null, $this->superAdmin());
        $expense = $service->post($expense, $this->superAdmin());

        $this->assertSame(['5300' => ['3500.00', '0.00'], '1101' => ['0.00', '3500.00']], $this->lines($expense->journal_entry_id));
        $this->assertSame('96500.00', $this->cashBalance($this->cashA->id));

        $this->expectException(InvalidStateException::class);
        $service->update($expense, ['amount' => '1'], null, $this->superAdmin());
    }

    public function test_an_expense_larger_than_the_cash_in_hand_is_refused(): void
    {
        $this->expectException(InsufficientBalanceException::class);
        app(ExpenseService::class)->create($this->data('5100', '100000.01'), null, true, $this->superAdmin());
    }

    public function test_the_account_must_be_of_the_right_type(): void
    {
        $this->expectException(FinanceException::class);
        app(ExpenseService::class)->create($this->data('4100', '10'), null, true, $this->superAdmin());
    }

    public function test_reversing_an_expense_gives_the_cash_back(): void
    {
        $expense = app(ExpenseService::class)->create($this->data('5500', '700'), null, true, $this->superAdmin());
        app(ExpenseService::class)->reverse($expense, 'Duplicate bill', $this->superAdmin());

        $this->assertSame(EntryStatus::Reversed, $expense->refresh()->status);
        $this->assertSame('100000.00', $this->cashBalance($this->cashA->id));
        $this->assertSame('0.00', $this->balance('5500'));
    }
}
