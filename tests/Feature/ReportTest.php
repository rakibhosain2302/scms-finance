<?php

namespace SCMS\Finance\Tests\Feature;

use SCMS\Finance\Services\DashboardService;
use SCMS\Finance\Services\ExpenseService;
use SCMS\Finance\Services\LoanRepaymentService;
use SCMS\Finance\Services\ReportService;
use SCMS\Finance\Services\SavingsTransactionService;
use SCMS\Finance\Support\Abilities;
use SCMS\Finance\Tests\FinanceTestCase;

class ReportTest extends FinanceTestCase
{
    private function scenario(): void
    {
        $account = $this->openSavings();
        $savings = app(SavingsTransactionService::class);
        $move = ['transaction_date' => today()->toDateString(), 'cash_bank_account_id' => $this->cashA->id];
        $savings->deposit($account, $move + ['amount' => '2000'], $this->superAdmin());
        $reversed = $savings->deposit($account, $move + ['amount' => '999'], $this->superAdmin());
        $savings->reverse($reversed, 'Typo', $this->superAdmin());
        $savings->withdraw($account, $move + ['amount' => '500'], $this->superAdmin());

        $loan = $this->disbursedLoan('12000');
        app(LoanRepaymentService::class)->repay($loan, $move + ['amount' => '1120', 'repayment_date' => today()->toDateString()], $this->superAdmin());
        app(ExpenseService::class)->create(['branch_id' => $this->branchA->id, 'voucher_date' => today()->toDateString(), 'account_id' => $this->account('5200')->id,
            'cash_bank_account_id' => $this->cashA->id, 'amount' => '800'], null, true, $this->superAdmin());
    }

    public function test_activity_counts_posted_entries_and_nets_out_reversals(): void
    {
        $this->scenario();
        $a = app(ReportService::class)->activity(null, today(), today());

        $this->assertSame('2000.00', $a['deposits']->toString());
        $this->assertSame('500.00', $a['withdrawals']->toString());
        $this->assertSame('1000.00', $a['principal_repaid']->toString());
        $this->assertSame('120.00', $a['interest']->toString());
        $this->assertSame('120.00', $a['income']->toString());
        $this->assertSame('800.00', $a['expense']->toString());
        // 2000 − 500 + 1120 − 800 (the loan was paid out two days ago).
        $this->assertSame('1820.00', $a['cash_net']->toString());
    }

    public function test_the_books_balance(): void
    {
        $this->scenario();
        $reports = app(ReportService::class);

        $tb = $this->ledger()->trialBalance(null, today());
        $this->assertTrue($tb['debit']->equals($tb['credit']));

        $bs = $reports->balanceSheet(null, today());
        $this->assertTrue($bs['total_asset']->equals($bs['total_liability']->plus($bs['total_equity'])));
        $this->assertSame('1500.00', $bs['total_liability']->toString());
        $this->assertSame('-680.00', $bs['unclosed']->toString());

        $book = $reports->cashbook([$this->branchA->id], today(), today());
        $this->assertTrue($book['closing']->equals($book['opening']->plus($book['receipts'])->minus($book['payments'])));
        $this->assertSame($this->cashBalance($this->cashA->id), $book['closing']->minus($this->cashBalance($this->bankA->id))->toString());
    }

    public function test_reports_are_limited_to_the_users_branches(): void
    {
        $this->scenario();
        $beta = $this->makeUser([Abilities::REPORT_VIEW, Abilities::DASHBOARD], [$this->branchB->id]);

        $summary = app(DashboardService::class)->summary($beta);
        $this->assertSame('50000.00', $summary['cash']->toString());
        $this->assertTrue($summary['savings']->isZero());

        $this->actingAs($beta)->get(route('finance.reports.trial-balance', ['branch_id' => $this->branchA->id]))->assertForbidden();
        $this->actingAs($this->makeUser([Abilities::REPORT_VIEW, Abilities::REPORT_ALL_BRANCHES], [$this->branchB->id]))
            ->get(route('finance.reports.trial-balance', ['branch_id' => $this->branchA->id]))->assertOk();
    }

    public function test_loan_portfolio_reads_the_ledger_and_the_schedule(): void
    {
        $this->scenario();
        $portfolio = app(ReportService::class)->loanPortfolio(null, today()->addMonths(3));

        $this->assertSame('11000.00', $portfolio['outstanding']->toString());
        $this->assertSame(1, $portfolio['overdue_loans']);
        $this->assertTrue($portfolio['overdue']->isPositive());
    }
}
