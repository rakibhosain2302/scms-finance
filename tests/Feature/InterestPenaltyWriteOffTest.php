<?php

namespace SCMS\Finance\Tests\Feature;

use SCMS\Finance\Enums\LoanStatus;
use SCMS\Finance\Enums\SavingsTransactionType;
use SCMS\Finance\Exceptions\FinanceException;
use SCMS\Finance\Exceptions\InvalidStateException;
use SCMS\Finance\Models\Loan;
use SCMS\Finance\Models\LoanInstallment;
use SCMS\Finance\Models\SavingsAccount;
use SCMS\Finance\Models\SavingsTransaction;
use SCMS\Finance\Services\LoanRepaymentService;
use SCMS\Finance\Services\LoanService;
use SCMS\Finance\Services\SavingsInterestService;
use SCMS\Finance\Services\SavingsService;
use SCMS\Finance\Services\SavingsTransactionService;
use SCMS\Finance\Support\Abilities;
use SCMS\Finance\Support\Money;
use SCMS\Finance\Support\PenaltyCalculator;
use SCMS\Finance\Tests\FinanceTestCase;

class InterestPenaltyWriteOffTest extends FinanceTestCase
{
    // --- Interest on savings ---

    private function fundedAt(int $daysAgo, string $amount, string $rate = '6'): SavingsAccount
    {
        $account = app(SavingsService::class)->open([
            'branch_id' => $this->branchA->id, 'member_id' => $this->memberA->id,
            'savings_scheme_id' => $this->scheme(['interest_rate' => $rate])->id, 'opened_on' => today()->subDays($daysAgo)->toDateString(),
        ], $this->superAdmin());
        app(SavingsTransactionService::class)->deposit($account, ['amount' => $amount, 'transaction_date' => today()->subDays($daysAgo)->toDateString(), 'cash_bank_account_id' => $this->cashA->id], $this->superAdmin());

        return $account;
    }

    private function postInterest(SavingsAccount $account, int $fromDaysAgo = 30, int $toDaysAgo = 1)
    {
        return app(SavingsInterestService::class)->post($account->scheme, $this->branchA->id,
            today()->subDays($fromDaysAgo), today()->subDays($toDaysAgo), today(), $this->superAdmin());
    }

    public function test_interest_is_the_daily_balance_times_the_rate_and_credits_the_member(): void
    {
        $account = $this->fundedAt(40, '10000');

        // 10,000 for 30 days at 6%: 300,000 × 6 / 36,500 = 49.315… → 49.32
        $run = $this->postInterest($account);

        $this->assertSame('49.32', Money::of($run->total)->toString());
        $this->assertSame(1, $run->accounts_count);
        $this->assertSame('10049.32', $this->ledger()->savingsBalance($account->id)->toString());
        $this->assertSame('49.32', $this->balance('5800'));
        $this->assertSame('10049.32', $this->balance('2100'));
        $this->assertSame('110000.00', $this->cashBalance($this->cashA->id), 'interest moves no cash');
        $this->assertSame(SavingsTransactionType::Interest, SavingsTransaction::query()->where('savings_interest_run_id', $run->id)->firstOrFail()->type);
    }

    public function test_a_withdrawal_inside_the_period_lowers_the_interest(): void
    {
        $account = $this->fundedAt(40, '10000');
        app(SavingsTransactionService::class)->withdraw($account, ['amount' => '4000', 'transaction_date' => today()->subDays(15)->toDateString(), 'cash_bank_account_id' => $this->cashA->id], $this->superAdmin());

        // 15 days at 10,000 and 15 days at 6,000: 240,000 × 6 / 36,500 = 39.45
        $preview = app(SavingsInterestService::class)->preview($account->scheme, $this->branchA->id, today()->subDays(30), today()->subDay());
        $this->assertSame('39.45', $preview->first()['interest']->toString());
        $this->assertSame('39.45', Money::of($this->postInterest($account)->total)->toString());
    }

    public function test_a_period_is_credited_once_until_its_interest_is_reversed(): void
    {
        $account = $this->fundedAt(40, '10000');
        $run = $this->postInterest($account);

        try {
            $this->postInterest($account, 10, 5);
            $this->fail('Overlapping interest was posted.');
        } catch (FinanceException $e) {
            $this->assertStringContainsString('already posted', $e->getMessage());
        }

        $transaction = SavingsTransaction::query()->where('savings_interest_run_id', $run->id)->firstOrFail();
        app(SavingsTransactionService::class)->reverse($transaction, 'Wrong rate', $this->superAdmin());
        $this->assertSame('10000.00', $this->ledger()->savingsBalance($account->id)->toString());
        $this->assertSame('0.00', $this->balance('5800'));

        $this->assertSame('49.32', Money::of($this->postInterest($account)->total)->toString());
    }

    public function test_interest_needs_its_own_ability_and_a_rate(): void
    {
        $account = $this->fundedAt(40, '10000', '0');
        $this->actingAs($this->makeUser([Abilities::SAVINGS_MANAGE], [$this->branchA->id]))
            ->get(route('finance.savings-interest.create'))->assertForbidden();

        $this->expectException(FinanceException::class);
        $this->postInterest($account);
    }

    public function test_the_interest_page_previews_and_posts(): void
    {
        $account = $this->fundedAt(40, '10000');
        $officer = $this->makeUser([Abilities::SAVINGS_INTEREST], [$this->branchA->id]);
        $period = ['savings_scheme_id' => $account->savings_scheme_id, 'branch_id' => $this->branchA->id,
            'period_from' => today()->subDays(30)->toDateString(), 'period_to' => today()->subDay()->toDateString()];

        $this->actingAs($officer)->get(route('finance.savings-interest.create', $period))->assertOk()->assertSee('49.32');
        $this->actingAs($officer)->post(route('finance.savings-interest.store'), $period + ['posting_date' => today()->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame('10049.32', $this->ledger()->savingsBalance($account->id)->toString());
    }

    // --- Loan penalty rules ---

    /** 3 monthly installments of which all have fallen due, the oldest 65 days ago. */
    private function lateLoan(array $product): Loan
    {
        $loans = app(LoanService::class);
        $loan = $loans->apply([
            'branch_id' => $this->branchA->id, 'member_id' => $this->memberA->id, 'loan_product_id' => $this->loanProduct($product + ['interest_method' => 'none', 'interest_rate' => '0'])->id,
            'principal' => '3000', 'installments_count' => 3, 'applied_on' => today()->subDays(100)->toDateString(),
        ], $this->makeUser([], [$this->branchA->id]));
        $loans->approve($loan, $this->superAdmin());

        return $loans->disburse($loan, ['disbursed_on' => today()->subDays(95)->toDateString(), 'first_due_on' => today()->subDays(65)->toDateString(), 'cash_bank_account_id' => $this->cashA->id], $this->superAdmin());
    }

    private function owed(Loan $loan): string
    {
        $unpaid = LoanInstallment::query()->where('loan_id', $loan->id)->unpaid()->orderBy('installment_no')->get();

        return Money::sum(app(PenaltyCalculator::class)->due($loan->product()->first(), $unpaid, today())->all())->toString();
    }

    public function test_a_fixed_penalty_is_owed_once_per_late_installment_and_cannot_be_overcharged(): void
    {
        $loan = $this->lateLoan(['penalty_type' => 'fixed', 'penalty_value' => '50', 'grace_days' => 0]);
        $this->assertSame('150.00', $this->owed($loan));

        $repay = fn (string $amount, string $penalty) => app(LoanRepaymentService::class)->repay($loan, ['amount' => $amount, 'penalty_amount' => $penalty,
            'repayment_date' => today()->toDateString(), 'cash_bank_account_id' => $this->cashA->id], $this->superAdmin());

        try {
            $repay('1200', '200');
            $this->fail('More penalty than the rule asks was taken.');
        } catch (FinanceException $e) {
            $this->assertStringContainsString('penalty owed', $e->getMessage());
        }

        $repayment = $repay('1150', '150');
        $this->assertSame('150.00', $this->balance('4300'));
        $this->assertSame('0.00', $this->owed($loan));
        $this->assertSame('150.00', Money::sum($repayment->allocations()->pluck('penalty'))->toString());

        app(LoanRepaymentService::class)->reverse($repayment, 'Wrong amount', $this->superAdmin());
        $this->assertSame('150.00', $this->owed($loan), 'reversing gives the penalty back to the installments');
        $this->assertSame('0.00', $this->balance('4300'));
    }

    public function test_grace_days_and_a_percentage_rule(): void
    {
        // Installments of 1,000 due 65, 35 and 5 days ago; 10 grace days leave the newest out.
        $loan = $this->lateLoan(['penalty_type' => 'percent', 'penalty_value' => '2', 'grace_days' => 10]);

        $this->assertSame('40.00', $this->owed($loan));
    }

    public function test_without_a_rule_any_penalty_is_entered_by_hand(): void
    {
        $loan = $this->lateLoan(['penalty_type' => 'none']);
        $this->assertSame('0.00', $this->owed($loan));

        app(LoanRepaymentService::class)->repay($loan, ['amount' => '1075', 'penalty_amount' => '75', 'repayment_date' => today()->toDateString(), 'cash_bank_account_id' => $this->cashA->id], $this->superAdmin());
        $this->assertSame('75.00', $this->balance('4300'));
    }

    // --- Write-off ---

    public function test_a_write_off_moves_the_principal_owed_to_a_loss_and_closes_the_loan(): void
    {
        $loan = $this->disbursedLoan('12000');
        app(LoanRepaymentService::class)->repay($loan, ['amount' => '1000', 'repayment_date' => today()->toDateString(), 'cash_bank_account_id' => $this->cashA->id], $this->superAdmin());
        $owed = $this->ledger()->loanOutstanding($loan->id)->toString();

        $loan = app(LoanService::class)->writeOff($loan, ['written_off_on' => today()->toDateString(), 'reason' => 'Member left the area'], $this->superAdmin());

        $this->assertSame(LoanStatus::WrittenOff, $loan->status);
        $this->assertSame('0.00', $this->ledger()->loanOutstanding($loan->id)->toString());
        $this->assertSame($owed, $this->balance('5700'));
        $this->assertSame([$owed, '0.00'], $this->lines($loan->write_off_entry_id)['5700']);
        $this->assertSame('0.00', $this->balance('1300'));

        $this->expectException(InvalidStateException::class);
        app(LoanRepaymentService::class)->repay($loan, ['amount' => '100', 'repayment_date' => today()->toDateString(), 'cash_bank_account_id' => $this->cashA->id], $this->superAdmin());
    }

    public function test_a_write_off_needs_a_reason_a_running_loan_and_its_ability(): void
    {
        $loan = $this->disbursedLoan();
        $viewer = $this->makeUser([Abilities::LOAN_VIEW, Abilities::LOAN_REPAY], [$this->branchA->id]);
        $this->actingAs($viewer)->post(route('finance.loans.write-off', $loan), ['written_off_on' => today()->toDateString(), 'reason' => 'Member left'])->assertForbidden();

        try {
            app(LoanService::class)->writeOff($loan, ['written_off_on' => today()->toDateString(), 'reason' => ' '], $this->superAdmin());
            $this->fail('A write-off without a reason went through.');
        } catch (FinanceException) {
            $this->assertSame(LoanStatus::Active, $loan->refresh()->status);
        }

        $officer = $this->makeUser([Abilities::LOAN_VIEW, Abilities::LOAN_WRITE_OFF], [$this->branchA->id]);
        $this->actingAs($officer)->post(route('finance.loans.write-off', $loan), ['written_off_on' => today()->toDateString(), 'reason' => 'Deceased, no estate'])
            ->assertSessionHasNoErrors();
        $this->assertSame(LoanStatus::WrittenOff, $loan->refresh()->status);
    }

    public function test_the_new_pages_render(): void
    {
        $loan = $this->lateLoan(['penalty_type' => 'fixed', 'penalty_value' => '50']);
        $admin = $this->superAdmin();

        $this->actingAs($admin)->get(route('finance.savings-interest.create'))->assertOk();
        $this->actingAs($admin)->get(route('finance.loan-repayments.create', $loan))->assertOk()->assertSee('150.00');
        $this->actingAs($admin)->get(route('finance.loans.show', $loan))->assertOk()->assertSee(__('Write off'));
        $this->actingAs($admin)->get(route('finance.loan-products.edit', $loan->loan_product_id))->assertOk();
    }
}
