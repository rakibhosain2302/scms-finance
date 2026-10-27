<?php

namespace SCMS\Finance\Tests\Feature;

use SCMS\Finance\Enums\EntryStatus;
use SCMS\Finance\Exceptions\BranchAccessException;
use SCMS\Finance\Exceptions\FinanceException;
use SCMS\Finance\Exceptions\InsufficientBalanceException;
use SCMS\Finance\Services\ReportService;
use SCMS\Finance\Services\SavingsService;
use SCMS\Finance\Services\SavingsTransactionService;
use SCMS\Finance\Tests\FinanceTestCase;

class SavingsTest extends FinanceTestCase
{
    private function move(string $type, $account, string $amount, $cash = null)
    {
        $data = ['amount' => $amount, 'transaction_date' => today()->toDateString(), 'cash_bank_account_id' => ($cash ?? $this->cashA)->id];
        $service = app(SavingsTransactionService::class);

        return $type === 'deposit' ? $service->deposit($account, $data, $this->superAdmin()) : $service->withdraw($account, $data, $this->superAdmin());
    }

    public function test_a_deposit_increases_the_member_liability_and_cash_never_income(): void
    {
        $account = $this->openSavings();
        $deposit = $this->move('deposit', $account, '1000');

        $this->assertSame(['1101' => ['1000.00', '0.00'], '2100' => ['0.00', '1000.00']], $this->lines($deposit->journal_entry_id));
        $this->assertSame('1000.00', $this->ledger()->savingsBalance($account->id)->toString());
        $this->assertSame('1000.00', $account->refresh()->balance);
        $this->assertSame('1000.00', $this->balance('2100'));
        $this->assertSame('101000.00', $this->cashBalance($this->cashA->id));
        $this->assertTrue(app(ReportService::class)->incomeStatement(null, today()->startOfYear(), today())['total_income']->isZero());
        $this->assertMatchesRegularExpression('/^DEP-\d{4}-\d{6}$/', $deposit->number);
    }

    public function test_a_withdrawal_decreases_the_liability_and_cash(): void
    {
        $account = $this->openSavings();
        $this->move('deposit', $account, '1000');
        $withdrawal = $this->move('withdrawal', $account, '400');

        $this->assertSame(['2100' => ['400.00', '0.00'], '1101' => ['0.00', '400.00']], $this->lines($withdrawal->journal_entry_id));
        $this->assertSame('600.00', $this->ledger()->savingsBalance($account->id)->toString());
        $this->assertSame('100600.00', $this->cashBalance($this->cashA->id));
    }

    public function test_more_than_the_balance_cannot_be_withdrawn(): void
    {
        $account = $this->openSavings();
        $this->move('deposit', $account, '500');

        try {
            $this->move('withdrawal', $account, '500.01');
            $this->fail('Overdrawn.');
        } catch (InsufficientBalanceException) {
        }

        $this->assertSame('500.00', $this->ledger()->savingsBalance($account->id)->toString());
        $this->assertSame(1, $account->transactions()->count());
    }

    public function test_the_minimum_balance_stays(): void
    {
        $account = $this->openSavings(['minimum_balance' => '100']);
        $this->move('deposit', $account, '500');

        $this->expectException(InsufficientBalanceException::class);
        $this->move('withdrawal', $account, '450');
    }

    public function test_scheme_limits_apply(): void
    {
        $account = $this->openSavings(['min_deposit' => '100', 'max_deposit' => '1000', 'allow_withdrawal' => false]);

        foreach ([['deposit', '50'], ['deposit', '1500']] as [$type, $amount]) {
            try {
                $this->move($type, $account, $amount);
                $this->fail("{$type} {$amount} was accepted.");
            } catch (FinanceException) {
            }
        }
        $this->move('deposit', $account, '500');
        $this->expectException(FinanceException::class);
        $this->move('withdrawal', $account, '10');
    }

    public function test_reversing_a_deposit_that_was_already_withdrawn_is_refused(): void
    {
        $account = $this->openSavings();
        $deposit = $this->move('deposit', $account, '1000');
        $withdrawal = $this->move('withdrawal', $account, '800');

        try {
            app(SavingsTransactionService::class)->reverse($deposit, 'Typo', $this->superAdmin());
            $this->fail('Balance went negative.');
        } catch (InsufficientBalanceException) {
        }

        app(SavingsTransactionService::class)->reverse($withdrawal, 'Paid by mistake', $this->superAdmin());
        $this->assertSame(EntryStatus::Reversed, $withdrawal->refresh()->status);
        $this->assertNotNull($withdrawal->reversal_entry_id);
        $this->assertSame('1000.00', $account->refresh()->balance);

        app(SavingsTransactionService::class)->reverse($deposit, 'Typo', $this->superAdmin());
        $this->assertSame('0.00', $this->ledger()->savingsBalance($account->id)->toString());
        $this->assertSame('100000.00', $this->cashBalance($this->cashA->id));
    }

    public function test_accounts_and_cash_must_belong_to_the_same_branch(): void
    {
        try {
            app(SavingsService::class)->open(['branch_id' => $this->branchA->id, 'member_id' => $this->memberB->id,
                'savings_scheme_id' => $this->scheme()->id, 'opened_on' => today()->toDateString()], $this->superAdmin());
            $this->fail('Member of branch B got an account in branch A.');
        } catch (BranchAccessException) {
        }

        $account = $this->openSavings();
        $this->expectException(BranchAccessException::class);
        $this->move('deposit', $account, '100', $this->cashB);
    }

    public function test_a_closed_account_takes_nothing_and_only_closes_empty(): void
    {
        $account = $this->openSavings();
        $this->move('deposit', $account, '100');

        try {
            app(SavingsService::class)->setStatus($account, \SCMS\Finance\Enums\SavingsAccountStatus::Closed, $this->superAdmin());
            $this->fail('Closed with money in it.');
        } catch (FinanceException) {
        }

        $this->move('withdrawal', $account, '100');
        app(SavingsService::class)->setStatus($account, \SCMS\Finance\Enums\SavingsAccountStatus::Closed, $this->superAdmin());
        $this->expectException(FinanceException::class);
        $this->move('deposit', $account, '100');
    }
}
