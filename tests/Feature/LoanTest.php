<?php

namespace SCMS\Finance\Tests\Feature;

use SCMS\Finance\Enums\EntryStatus;
use SCMS\Finance\Enums\InstallmentStatus;
use SCMS\Finance\Enums\LoanStatus;
use SCMS\Finance\Exceptions\FinanceException;
use SCMS\Finance\Exceptions\InvalidStateException;
use SCMS\Finance\Models\Loan;
use SCMS\Finance\Services\LoanRepaymentService;
use SCMS\Finance\Services\LoanService;
use SCMS\Finance\Services\ReportService;
use SCMS\Finance\Tests\FinanceTestCase;

class LoanTest extends FinanceTestCase
{
    private function repay(Loan $loan, string $amount, string $penalty = '0')
    {
        return app(LoanRepaymentService::class)->repay($loan, [
            'amount' => $amount, 'penalty_amount' => $penalty, 'repayment_date' => today()->toDateString(), 'cash_bank_account_id' => $this->cashA->id,
        ], $this->superAdmin());
    }

    public function test_nobody_approves_their_own_application(): void
    {
        $maker = $this->makeUser([], [$this->branchA->id]);
        $loan = app(LoanService::class)->apply([
            'branch_id' => $this->branchA->id, 'member_id' => $this->memberA->id, 'loan_product_id' => $this->loanProduct()->id,
            'principal' => '5000', 'installments_count' => 5, 'applied_on' => today()->toDateString(),
        ], $maker);
        $this->assertSame(LoanStatus::Applied, $loan->status);
        $this->assertMatchesRegularExpression('/^LOAN-\d{4}-\d{6}$/', $loan->loan_number);

        $this->expectException(FinanceException::class);
        app(LoanService::class)->approve($loan, $maker);
    }

    public function test_product_limits_apply_to_applications(): void
    {
        $this->expectException(FinanceException::class);
        app(LoanService::class)->apply([
            'branch_id' => $this->branchA->id, 'member_id' => $this->memberA->id, 'loan_product_id' => $this->loanProduct(['max_amount' => '10000'])->id,
            'principal' => '20000', 'installments_count' => 5, 'applied_on' => today()->toDateString(),
        ], $this->superAdmin());
    }

    public function test_disbursement_moves_cash_into_the_receivable_and_is_not_an_expense(): void
    {
        $loan = $this->disbursedLoan('12000', ['processing_fee_rate' => '1']);

        $this->assertSame(LoanStatus::Active, $loan->status);
        $this->assertSame(['1300' => ['12000.00', '0.00'], '1101' => ['0.00', '11880.00'], '4400' => ['0.00', '120.00']], $this->lines($loan->disbursement_entry_id));
        $this->assertSame('88120.00', $this->cashBalance($this->cashA->id));
        $this->assertSame('12000.00', $this->ledger()->loanOutstanding($loan->id)->toString());
        $this->assertSame('12000.00', $loan->refresh()->principal_outstanding);
        $this->assertCount(12, $loan->installments);
        $this->assertSame('1440.00', $loan->total_interest); // 12 % flat for a year
        $this->assertTrue(app(ReportService::class)->incomeStatement(null, today()->startOfYear(), today())['total_expense']->isZero());
        $this->expectException(InvalidStateException::class);
        app(LoanService::class)->disburse($loan, ['disbursed_on' => today()->toDateString(), 'first_due_on' => today()->addMonth()->toDateString(), 'cash_bank_account_id' => $this->cashA->id], $this->superAdmin());
    }

    public function test_a_repayment_separates_principal_from_interest_and_penalty(): void
    {
        $loan = $this->disbursedLoan();
        $repayment = $this->repay($loan, '1170', '50'); // installment 1 = 1000 + 120, plus 50 penalty

        $this->assertSame('1000.00', $repayment->principal_amount);
        $this->assertSame('120.00', $repayment->interest_amount);
        $this->assertSame(['1101' => ['1170.00', '0.00'], '1300' => ['0.00', '1000.00'], '4200' => ['0.00', '120.00'], '4300' => ['0.00', '50.00']], $this->lines($repayment->journal_entry_id));
        $this->assertSame('11000.00', $this->ledger()->loanOutstanding($loan->id)->toString());
        $this->assertSame(LoanStatus::PartiallyPaid, $loan->refresh()->status);
        $this->assertSame(InstallmentStatus::Paid, $loan->installments[0]->status);
        $this->assertSame('170.00', app(ReportService::class)->incomeStatement(null, today()->startOfYear(), today())['total_income']->toString());
    }

    public function test_a_partial_payment_pays_interest_first_and_leaves_the_installment_partial(): void
    {
        $loan = $this->disbursedLoan();
        $repayment = $this->repay($loan, '500');

        $this->assertSame('120.00', $repayment->interest_amount);
        $this->assertSame('380.00', $repayment->principal_amount);
        $first = $loan->installments()->first();
        $this->assertSame(InstallmentStatus::Partial, $first->status);
        $this->assertSame('620.00', $first->remaining()->toString());
    }

    public function test_a_loan_cannot_be_overpaid(): void
    {
        $loan = $this->disbursedLoan('1000', ['interest_method' => 'none', 'interest_rate' => '0'], 2);

        $this->expectException(FinanceException::class);
        $this->repay($loan, '1000.01');
    }

    public function test_paying_everything_completes_the_loan(): void
    {
        $loan = $this->disbursedLoan('1000', ['interest_method' => 'flat', 'interest_rate' => '12'], 2);
        $this->repay($loan, '600');
        $this->repay($loan, '420'); // 1000 principal + 20 interest (12 % a year for 2 months)

        $loan->refresh();
        $this->assertSame(LoanStatus::Completed, $loan->status);
        $this->assertNotNull($loan->closed_on);
        $this->assertSame('0.00', $this->ledger()->loanOutstanding($loan->id)->toString());
        $this->assertSame(0, $loan->installments()->unpaid()->count());
    }

    public function test_only_the_latest_repayment_is_reversed_and_it_gives_the_installments_back(): void
    {
        $loan = $this->disbursedLoan();
        $first = $this->repay($loan, '1120');
        $second = $this->repay($loan, '300');

        try {
            app(LoanRepaymentService::class)->reverse($first, 'Wrong loan', $this->superAdmin());
            $this->fail('Older repayment reversed first.');
        } catch (InvalidStateException) {
        }

        app(LoanRepaymentService::class)->reverse($second, 'Bounced', $this->superAdmin());
        $this->assertSame(EntryStatus::Reversed, $second->refresh()->status);
        $this->assertSame(InstallmentStatus::Pending, $loan->installments()->where('installment_no', 2)->first()->status);
        $this->assertSame('11000.00', $this->ledger()->loanOutstanding($loan->id)->toString());

        app(LoanRepaymentService::class)->reverse($first, 'Wrong loan', $this->superAdmin());
        $this->assertSame(LoanStatus::Active, $loan->refresh()->status);
        $this->assertSame('12000.00', $loan->principal_outstanding);
        $this->assertSame(0, $loan->installments()->where('status', '!=', 'pending')->count());
    }

    public function test_a_disbursement_is_reversed_only_before_repayments(): void
    {
        $loan = $this->disbursedLoan();
        $this->repay($loan, '100');

        try {
            app(LoanService::class)->reverseDisbursement($loan, 'Error', $this->superAdmin());
            $this->fail('Disbursement reversed with repayments.');
        } catch (InvalidStateException) {
        }

        $other = $this->disbursedLoan('5000');
        app(LoanService::class)->reverseDisbursement($other, 'Paid to the wrong member', $this->superAdmin());
        $this->assertSame(LoanStatus::Cancelled, $other->refresh()->status);
        $this->assertSame('0.00', $this->ledger()->loanOutstanding($other->id)->toString());
    }

    public function test_declining_balance_interest_follows_the_principal(): void
    {
        $loan = $this->disbursedLoan('10000', ['interest_method' => 'declining', 'interest_rate' => '12']);
        $this->assertSame('100.00', $loan->installments[0]->interest_due);
        $this->assertSame('10000.00', \SCMS\Finance\Support\Money::sum($loan->installments->pluck('principal_due'))->toString());
    }
}
