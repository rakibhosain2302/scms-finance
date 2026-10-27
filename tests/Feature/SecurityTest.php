<?php

namespace SCMS\Finance\Tests\Feature;

use SCMS\Finance\Enums\YearStatus;
use SCMS\Finance\Exceptions\BranchAccessException;
use SCMS\Finance\Exceptions\ClosedFinancialYearException;
use SCMS\Finance\Models\FinancialYear;
use SCMS\Finance\Models\SavingsTransaction;
use SCMS\Finance\Services\IncomeService;
use SCMS\Finance\Services\ExpenseService;
use SCMS\Finance\Services\FinancialYearService;
use SCMS\Finance\Services\SavingsTransactionService;
use SCMS\Finance\Services\YearEndService;
use SCMS\Finance\Support\Abilities;
use SCMS\Finance\Tests\FinanceTestCase;

class SecurityTest extends FinanceTestCase
{
    public function test_without_the_ability_a_page_is_forbidden(): void
    {
        $user = $this->makeUser([Abilities::SAVINGS_VIEW], [$this->branchA->id]);

        $this->actingAs($user)->get(route('finance.savings-accounts.index'))->assertOk();
        $this->actingAs($user)->get(route('finance.incomes.index'))->assertForbidden();
        $this->actingAs($user)->post(route('finance.incomes.store'), [])->assertForbidden();
    }

    public function test_a_branch_user_never_sees_or_touches_another_branch(): void
    {
        $account = $this->openSavings();
        $alpha = $this->makeUser([Abilities::SAVINGS_VIEW, Abilities::SAVINGS_DEPOSIT], [$this->branchA->id]);
        $beta = $this->makeUser([Abilities::SAVINGS_VIEW, Abilities::SAVINGS_DEPOSIT], [$this->branchB->id]);

        $this->actingAs($alpha)->get(route('finance.savings-accounts.show', $account))->assertOk();
        $this->actingAs($beta)->get(route('finance.savings-accounts.show', $account))->assertNotFound();

        $this->actingAs($beta)->post(route('finance.savings-transactions.store'), [
            'savings_account_id' => $account->id, 'type' => 'deposit', 'amount' => '100',
            'transaction_date' => today()->toDateString(), 'cash_bank_account_id' => $this->cashB->id,
        ])->assertNotFound();
        $this->assertSame(0, SavingsTransaction::query()->withoutBranchScope()->count());

        // The service checks too, whatever the caller sends.
        $this->expectException(BranchAccessException::class);
        app(SavingsTransactionService::class)->deposit($account, ['amount' => '100', 'transaction_date' => today()->toDateString(), 'cash_bank_account_id' => $this->cashA->id], $beta);
    }

    public function test_a_submitted_branch_id_is_checked_against_the_users_branches(): void
    {
        $alpha = $this->makeUser([Abilities::INCOME_VIEW, Abilities::INCOME_CREATE, Abilities::INCOME_POST], [$this->branchA->id]);

        $this->actingAs($alpha)->post(route('finance.incomes.store'), [
            'branch_id' => $this->branchB->id, 'voucher_date' => today()->toDateString(), 'account_id' => $this->account('4100')->id,
            'cash_bank_account_id' => $this->cashB->id, 'amount' => '10', 'post' => '1',
        ])->assertSessionHasErrors('branch_id');

        $this->actingAs($alpha)->post(route('finance.incomes.store'), [
            'branch_id' => $this->branchA->id, 'voucher_date' => today()->toDateString(), 'account_id' => $this->account('4100')->id,
            'cash_bank_account_id' => $this->cashB->id, 'amount' => '10', 'post' => '1',
        ])->assertSessionHasErrors('finance');
        $this->assertSame('0.00', $this->balance('4100'));

        $this->actingAs($alpha)->post(route('finance.incomes.store'), [
            'branch_id' => $this->branchA->id, 'voucher_date' => today()->toDateString(), 'account_id' => $this->account('4100')->id,
            'cash_bank_account_id' => $this->cashA->id, 'amount' => '10', 'post' => '1',
        ])->assertRedirect();
        $this->assertSame('10.00', $this->balance('4100'));
    }

    public function test_reversing_needs_its_own_ability(): void
    {
        $income = app(IncomeService::class)->create(['branch_id' => $this->branchA->id, 'voucher_date' => today()->toDateString(),
            'account_id' => $this->account('4100')->id, 'cash_bank_account_id' => $this->cashA->id, 'amount' => '10'], null, true, $this->superAdmin());
        $clerk = $this->makeUser([Abilities::INCOME_VIEW, Abilities::INCOME_CREATE], [$this->branchA->id]);

        $this->actingAs($clerk)->post(route('finance.incomes.reverse', $income->id), ['reason' => 'mistake'])->assertForbidden();
        $this->actingAs($this->makeUser([Abilities::INCOME_VIEW, Abilities::REVERSE], [$this->branchA->id]))
            ->post(route('finance.incomes.reverse', $income->id), ['reason' => 'mistake'])->assertRedirect();
        $this->assertSame('0.00', $this->balance('4100'));
    }

    public function test_a_closed_year_takes_no_postings(): void
    {
        $old = new FinancialYear(['name' => 'Closed', 'starts_on' => $this->year->starts_on->copy()->subYear(), 'ends_on' => $this->year->starts_on->copy()->subDay()]);
        $old->forceFill(['status' => YearStatus::Closed->value])->save();
        $account = $this->openSavings();

        $this->expectException(ClosedFinancialYearException::class);
        app(SavingsTransactionService::class)->deposit($account, ['amount' => '100', 'transaction_date' => $old->ends_on->toDateString(), 'cash_bank_account_id' => $this->cashA->id], $this->superAdmin());
    }

    public function test_closing_a_year_moves_income_and_expense_to_surplus_and_locks_it(): void
    {
        $data = fn ($code, $amount) => ['branch_id' => $this->branchA->id, 'voucher_date' => today()->toDateString(), 'account_id' => $this->account($code)->id, 'cash_bank_account_id' => $this->cashA->id, 'amount' => $amount];
        app(IncomeService::class)->create($data('4100', '5000'), null, true, $this->superAdmin());
        app(ExpenseService::class)->create($data('5100', '1500'), null, true, $this->superAdmin());

        $entries = app(YearEndService::class)->close($this->year, $this->superAdmin());

        $this->assertCount(1, $entries);
        $this->assertSame(['4100' => ['5000.00', '0.00'], '5100' => ['0.00', '1500.00'], '3200' => ['0.00', '3500.00']], $this->lines($entries[0]));
        $this->assertSame('0.00', $this->balance('4100'));
        $this->assertSame('3500.00', $this->balance('3200'));
        $this->assertSame('closed', $this->year->refresh()->status->value);

        $this->expectException(ClosedFinancialYearException::class);
        app(IncomeService::class)->create($data('4100', '1'), null, true, $this->superAdmin());
    }

    public function test_financial_years_cannot_overlap(): void
    {
        $this->expectException(\SCMS\Finance\Exceptions\FinanceException::class);
        app(FinancialYearService::class)->create(['name' => 'Overlap', 'starts_on' => today()->toDateString(), 'ends_on' => today()->addYear()->toDateString()]);
    }

    public function test_pages_render_for_a_super_admin(): void
    {
        $loan = $this->disbursedLoan();
        $account = $this->openSavings();
        $admin = $this->superAdmin();

        foreach ([
            route('finance.dashboard'), route('finance.accounts.index'), route('finance.journals.index'), route('finance.journals.create'),
            route('finance.cash-bank.index'), route('finance.cash-bank.show', $this->cashA), route('finance.savings-accounts.show', $account),
            route('finance.savings-transactions.create', ['type' => 'deposit', 'account' => $account->id]), route('finance.loans.show', $loan),
            route('finance.loan-repayments.create', $loan), route('finance.incomes.create'), route('finance.expenses.index'), route('finance.transfers.create'),
            route('finance.reports.activity', ['group' => 'month']), route('finance.reports.cashbook'), route('finance.reports.trial-balance'),
            route('finance.reports.income-statement'), route('finance.reports.balance-sheet'), route('finance.reports.loans', ['overdue' => 1]),
            route('finance.reports.savings'), route('finance.reports.branches'), route('finance.reports.ledger', ['account_id' => $this->account('1101')->id]),
            route('finance.financial-years.index'), route('finance.savings-schemes.index'), route('finance.loan-products.create'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }
}
