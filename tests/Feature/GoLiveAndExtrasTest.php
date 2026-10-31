<?php

namespace SCMS\Finance\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Nit\Master\Models\Approval;
use Nit\Master\Support\Approvals;
use SCMS\Finance\Enums\EntryStatus;
use SCMS\Finance\Enums\LoanStatus;
use SCMS\Finance\Enums\YearStatus;
use SCMS\Finance\Exceptions\FinanceException;
use SCMS\Finance\Exceptions\InsufficientBalanceException;
use SCMS\Finance\Models\FinancialYear;
use SCMS\Finance\Models\Loan;
use SCMS\Finance\Models\LoanInstallment;
use SCMS\Finance\Models\SavingsTransaction;
use SCMS\Finance\Models\ShareTransaction;
use SCMS\Finance\Services\DividendService;
use SCMS\Finance\Services\IncomeService;
use SCMS\Finance\Services\LoanAdjustmentService;
use SCMS\Finance\Services\LoanService;
use SCMS\Finance\Services\ReportService;
use SCMS\Finance\Services\SavingsService;
use SCMS\Finance\Services\SavingsTransactionService;
use SCMS\Finance\Services\ShareService;
use SCMS\Finance\Services\YearEndService;
use SCMS\Finance\Support\Abilities;
use SCMS\Finance\Support\Money;
use SCMS\Finance\Tests\FinanceTestCase;

class GoLiveAndExtrasTest extends FinanceTestCase
{
    // --- Share capital ---

    private function share(string $type, string $amount, ?string $date = null)
    {
        return app(ShareService::class)->post(['branch_id' => $this->branchA->id, 'member_id' => $this->memberA->id, 'type' => $type, 'amount' => $amount,
            'transaction_date' => $date ?? today()->toDateString(), 'cash_bank_account_id' => $this->cashA->id], $this->superAdmin());
    }

    public function test_shares_are_equity_and_cannot_be_refunded_beyond_what_is_held(): void
    {
        $this->share(ShareTransaction::PURCHASE, '5000');
        $this->assertSame('5000.00', app(ShareService::class)->balance($this->memberA->id)->toString());
        $this->assertSame('5000.00', $this->balance('3300'));
        $this->assertSame('105000.00', $this->cashBalance($this->cashA->id));

        $refund = $this->share(ShareTransaction::REFUND, '2000');
        $this->assertSame('3000.00', app(ShareService::class)->balance($this->memberA->id)->toString());

        try {
            $this->share(ShareTransaction::REFUND, '3500');
            $this->fail('More was refunded than held.');
        } catch (InsufficientBalanceException) {
        }

        app(ShareService::class)->reverse($refund, 'Wrong member', $this->superAdmin());
        $this->assertSame('5000.00', app(ShareService::class)->balance($this->memberA->id)->toString());
    }

    public function test_with_a_share_price_the_amount_is_a_whole_number_of_shares(): void
    {
        config(['scms-finance.share_price' => '100']);
        $this->assertSame(25, $this->share(ShareTransaction::PURCHASE, '2500')->shares);

        $this->expectException(FinanceException::class);
        $this->share(ShareTransaction::PURCHASE, '250.50');
    }

    // --- Running loans brought in, reschedule, waiver, recovery ---

    private function imported(): Loan
    {
        return app(LoanAdjustmentService::class)->importRunning([
            'branch_id' => $this->branchA->id, 'member_id' => $this->memberA->id, 'loan_product_id' => $this->loanProduct(['interest_method' => 'none', 'interest_rate' => '0'])->id,
            'outstanding_principal' => '6000', 'installments_count' => 6, 'next_due_on' => today()->addMonth()->toDateString(),
            'opened_on' => today()->toDateString(), 'original_principal' => '12000', 'reference' => 'OLD-17',
        ], $this->superAdmin());
    }

    public function test_a_running_loan_is_brought_in_against_opening_equity_without_cash(): void
    {
        $loan = $this->imported();

        $this->assertSame(LoanStatus::Active, $loan->status);
        $this->assertTrue($loan->imported);
        $this->assertSame('6000.00', $this->ledger()->loanOutstanding($loan->id)->toString());
        $this->assertSame(6, LoanInstallment::query()->where('loan_id', $loan->id)->count());
        $this->assertSame('100000.00', $this->cashBalance($this->cashA->id));
        // Opening equity: the drawers (150,000) plus the loan brought in, an asset like them.
        $this->assertSame('156000.00', $this->balance('3100'));
    }

    public function test_running_loans_are_imported_from_csv_and_checked_first(): void
    {
        $this->loanProduct(['code' => 'GEN', 'interest_method' => 'none', 'interest_rate' => '0']);
        $csv = "branch_code,member_no,product_code,outstanding_principal,installments_left,next_due_on,opened_on,original_principal,reference\n"
            ."ALP,A-1,GEN,5000,5,".today()->addMonth()->toDateString().','.today()->toDateString().",10000,OLD-1\n"
            ."BET,B-1,GEN,5000,5,".today()->addMonth()->toDateString().','.today()->toDateString().",,OLD-2\n"
            ."ALP,NOPE,GEN,5000,5,".today()->addMonth()->toDateString().','.today()->toDateString().",,OLD-3\n";
        $file = fn () => UploadedFile::fake()->createWithContent('loans.csv', $csv);
        $officer = $this->makeUser([Abilities::LOAN_IMPORT], [$this->branchA->id]);

        $this->actingAs($officer)->post(route('finance.loans.import.csv'), ['file' => $file(), 'dry_run' => '1'])->assertRedirect();
        $this->assertSame(0, Loan::query()->withoutBranchScope()->count(), 'check only saves nothing');
        $report = session('import_report');
        $this->assertSame(1, $report['created']);
        $this->assertCount(2, $report['errors'], 'another branch and an unknown member');

        $this->actingAs($officer)->post(route('finance.loans.import.csv'), ['file' => $file()])->assertRedirect();
        $this->assertSame(1, Loan::query()->withoutBranchScope()->count());
    }

    public function test_reschedule_spreads_what_is_owed_without_new_interest(): void
    {
        $loan = $this->disbursedLoan('12000');
        $before = Money::sum(LoanInstallment::query()->where('loan_id', $loan->id)->get()->map->remaining()->all());

        app(LoanAdjustmentService::class)->reschedule($loan, ['installments_count' => 24, 'first_due_on' => today()->addMonths(2)->toDateString(), 'reason' => 'Flood'], $this->superAdmin());

        $unpaid = LoanInstallment::query()->where('loan_id', $loan->id)->unpaid()->get();
        $this->assertCount(24, $unpaid);
        $this->assertSame($before->toString(), Money::sum($unpaid->map->remaining()->all())->toString());
        $this->assertSame('12000.00', $this->ledger()->loanOutstanding($loan->id)->toString(), 'nothing is posted');
        $this->assertNotNull($loan->refresh()->rescheduled_at);
    }

    public function test_waived_interest_is_never_income_and_settles_the_loan(): void
    {
        $loan = $this->disbursedLoan('12000');
        $interest = Money::sum(LoanInstallment::query()->where('loan_id', $loan->id)->get()->map->interestRemaining()->all());
        $this->assertTrue($interest->isPositive());

        app(LoanAdjustmentService::class)->waiveInterest($loan, $interest, 'Early settlement', $this->superAdmin());
        $this->assertSame('0.00', Money::sum(LoanInstallment::query()->where('loan_id', $loan->id)->get()->map->interestRemaining()->all())->toString());
        $this->assertSame($interest->toString(), Money::sum(LoanInstallment::query()->where('loan_id', $loan->id)->pluck('interest_waived'))->toString());

        app(\SCMS\Finance\Services\LoanRepaymentService::class)->repay($loan, ['amount' => '12000', 'repayment_date' => today()->toDateString(), 'cash_bank_account_id' => $this->cashA->id], $this->superAdmin());
        $this->assertSame(LoanStatus::Completed, $loan->refresh()->status);
        $this->assertSame('0.00', $this->balance('4200'));
    }

    public function test_money_recovered_after_a_write_off_is_income_up_to_the_loss(): void
    {
        $loan = app(LoanService::class)->writeOff($this->disbursedLoan('10000'), ['written_off_on' => today()->toDateString(), 'reason' => 'Left the area'], $this->superAdmin());
        $recover = fn (string $amount) => app(LoanAdjustmentService::class)->recover($loan, ['amount' => $amount, 'recovery_date' => today()->toDateString(), 'cash_bank_account_id' => $this->cashA->id], $this->superAdmin());

        $recovery = $recover('4000');
        $this->assertSame('4000.00', $this->balance('4700'));
        $this->assertSame(LoanStatus::WrittenOff, $loan->refresh()->status);

        try {
            $recover('6500');
            $this->fail('More than the loss was recovered.');
        } catch (FinanceException) {
        }

        app(LoanAdjustmentService::class)->reverseRecovery($recovery, 'Cheque bounced', $this->superAdmin());
        $this->assertSame('0.00', $this->balance('4700'));
    }

    public function test_a_product_can_require_guarantors(): void
    {
        $product = $this->loanProduct(['guarantors_required' => 1]);
        $apply = fn (array $guarantors) => app(LoanService::class)->apply(['branch_id' => $this->branchA->id, 'member_id' => $this->memberA->id, 'loan_product_id' => $product->id,
            'principal' => '5000', 'installments_count' => 6, 'applied_on' => today()->toDateString(), 'collateral' => 'Gold ring', 'guarantors' => $guarantors], $this->superAdmin());

        try {
            $apply([]);
            $this->fail('Applied without the guarantor the product needs.');
        } catch (FinanceException) {
        }

        $loan = $apply([['name' => 'Karim', 'relation' => 'Brother', 'phone' => '01711111111']]);
        $this->assertSame('Karim', $loan->guarantors()->value('name'));
        $this->assertSame('Gold ring', $loan->collateral);
    }

    // --- Savings: fixed term, arrears, approval of large withdrawals ---

    public function test_a_fixed_term_account_cannot_be_drawn_before_maturity(): void
    {
        $scheme = $this->scheme(['term_months' => 12, 'withdraw_before_maturity' => false]);
        $account = app(SavingsService::class)->open(['branch_id' => $this->branchA->id, 'member_id' => $this->memberA->id, 'savings_scheme_id' => $scheme->id,
            'opened_on' => today()->subDays(10)->toDateString()], $this->superAdmin());
        $this->assertSame(today()->subDays(10)->addMonthsNoOverflow(12)->toDateString(), $account->maturity_date->toDateString());
        app(SavingsTransactionService::class)->deposit($account, ['amount' => '1000', 'transaction_date' => today()->toDateString(), 'cash_bank_account_id' => $this->cashA->id], $this->superAdmin());

        $this->expectException(FinanceException::class);
        app(SavingsTransactionService::class)->withdraw($account, ['amount' => '100', 'transaction_date' => today()->toDateString(), 'cash_bank_account_id' => $this->cashA->id], $this->superAdmin());
    }

    public function test_regular_savers_behind_on_their_deposit_are_listed(): void
    {
        $scheme = $this->scheme(['frequency' => 'monthly', 'min_deposit' => '500']);
        $account = app(SavingsService::class)->open(['branch_id' => $this->branchA->id, 'member_id' => $this->memberA->id, 'savings_scheme_id' => $scheme->id,
            'opened_on' => today()->subMonthsNoOverflow(2)->toDateString()], $this->superAdmin());
        app(SavingsTransactionService::class)->deposit($account, ['amount' => '500', 'transaction_date' => today()->toDateString(), 'cash_bank_account_id' => $this->cashA->id], $this->superAdmin());

        // Three monthly deposits fell due (2 months ago, 1 month ago, today); one was made.
        $rows = app(ReportService::class)->savingsDues(null, today()->subYear(), today());
        $this->assertCount(1, $rows);
        $this->assertSame(3, $rows[0]['periods']);
        $this->assertSame('1000.00', $rows[0]['arrears']->toString());
    }

    public function test_a_withdrawal_above_the_limit_waits_for_a_second_person(): void
    {
        config(['scms-finance.rules.withdrawal_approval_above' => '1000']);
        $account = $this->openSavings();
        app(SavingsTransactionService::class)->deposit($account, ['amount' => '8000', 'transaction_date' => today()->toDateString(), 'cash_bank_account_id' => $this->cashA->id], $this->superAdmin());
        $cashier = $this->makeUser([Abilities::SAVINGS_VIEW, Abilities::SAVINGS_WITHDRAW], [$this->branchA->id]);

        $this->actingAs($cashier)->post(route('finance.savings-transactions.store'), ['type' => 'withdrawal', 'savings_account_id' => $account->id,
            'amount' => '5000', 'transaction_date' => today()->toDateString(), 'cash_bank_account_id' => $this->cashA->id])->assertSessionHasNoErrors();
        $this->assertSame('8000.00', $this->ledger()->savingsBalance($account->id)->toString(), 'nothing moves before approval');
        $approval = Approval::query()->where('type', 'finance_withdrawal')->firstOrFail();

        $manager = $this->makeUser([Abilities::WITHDRAWAL_APPROVE], [$this->branchA->id]);
        $this->actingAs($manager);
        $this->assertSame('approved', Approvals::approve($approval, $manager)->status);
        $this->assertSame('3000.00', $this->ledger()->savingsBalance($account->id)->toString());

        // A withdrawal the balance cannot cover is refused at once, not sent for approval.
        $this->actingAs($cashier)->post(route('finance.savings-transactions.store'), ['type' => 'withdrawal', 'savings_account_id' => $account->id,
            'amount' => '4000', 'transaction_date' => today()->toDateString(), 'cash_bank_account_id' => $this->cashA->id])->assertSessionHasErrors('finance');
        $this->assertSame(1, Approval::query()->count());
    }

    // --- Reports and dividend ---

    public function test_receipts_and_payments_run_from_opening_to_closing(): void
    {
        $account = $this->openSavings();
        app(SavingsTransactionService::class)->deposit($account, ['amount' => '3000', 'transaction_date' => today()->toDateString(), 'cash_bank_account_id' => $this->cashA->id], $this->superAdmin());
        $this->disbursedLoan('12000');

        $r = app(ReportService::class)->receiptsAndPayments([$this->branchA->id], today()->subDays(5), today());

        $this->assertSame('100000.00', $r['opening']->toString());
        $this->assertSame($r['closing']->toString(), $r['opening']->plus($r['total_receipts'])->minus($r['total_payments'])->toString());
        $this->assertSame('91000.00', $r['closing']->toString());
    }

    public function test_dividend_on_shares_comes_out_of_the_closed_year_surplus(): void
    {
        $old = new FinancialYear(['name' => 'FY-old', 'starts_on' => $this->year->starts_on->copy()->subYear(), 'ends_on' => $this->year->starts_on->copy()->subDay()]);
        $old->forceFill(['status' => YearStatus::Open->value])->save();
        $inOld = $old->ends_on->copy()->subMonth()->toDateString();
        $this->share(ShareTransaction::PURCHASE, '10000', $inOld);
        app(IncomeService::class)->create(['branch_id' => $this->branchA->id, 'voucher_date' => $inOld, 'account_id' => $this->account('4100')->id,
            'cash_bank_account_id' => $this->cashA->id, 'amount' => '5000'], null, true, $this->superAdmin());
        app(YearEndService::class)->close($old, $this->superAdmin());
        $account = $this->openSavings();

        $run = app(DividendService::class)->post($old->refresh(), '10', $account->scheme, today(), $this->superAdmin());

        $this->assertSame('1000.00', Money::of($run->total)->toString());
        $this->assertSame('1000.00', $this->ledger()->savingsBalance($account->id)->toString());
        $this->assertSame('4000.00', $this->balance('3200'), 'surplus 5,000 less the dividend');

        $this->expectException(FinanceException::class);
        app(DividendService::class)->post($old, '10', $account->scheme, today(), $this->superAdmin());
    }

    // --- SMS and forms ---

    public function test_members_get_an_sms_for_a_deposit_and_a_reminder_before_an_installment(): void
    {
        $sent = [];
        $this->app->instance(\Nit\Master\Contracts\SmsSender::class, new class($sent) implements \Nit\Master\Contracts\SmsSender {
            public function __construct(private array &$sent) {}

            public function send(string $to, string $message): void
            {
                $this->sent[] = [$to, $message];
            }
        });
        config(['scms-finance.sms.notify' => true]);
        $this->memberA->forceFill(['phone' => '01712345678'])->save();

        $account = $this->openSavings();
        app(SavingsTransactionService::class)->deposit($account, ['amount' => '700', 'transaction_date' => today()->toDateString(), 'cash_bank_account_id' => $this->cashA->id], $this->superAdmin());
        $this->assertSame('01712345678', $sent[0][0] ?? null);
        $this->assertStringContainsString('700.00', $sent[0][1]);

        $loan = $this->disbursedLoan('12000');
        LoanInstallment::query()->where('loan_id', $loan->id)->where('installment_no', 1)->update(['due_date' => today()->addDays(2)->toDateString()]);
        $sent = [];
        $this->artisan('finance:loan-reminders', ['--days' => 2])->assertSuccessful();
        $this->assertCount(1, $sent);
        $this->assertStringContainsString($loan->loan_number, $sent[0][1]);
    }

    public function test_reason_forms_refuse_before_validating(): void
    {
        $account = $this->openSavings();
        $deposit = app(SavingsTransactionService::class)->deposit($account, ['amount' => '100', 'transaction_date' => today()->toDateString(), 'cash_bank_account_id' => $this->cashA->id], $this->superAdmin());
        $clerk = $this->makeUser([Abilities::SAVINGS_VIEW], [$this->branchA->id]);

        $this->actingAs($clerk)->post(route('finance.savings-transactions.reverse', $deposit), ['reason' => 'x'])->assertForbidden();
        $this->assertSame(EntryStatus::Posted, SavingsTransaction::query()->find($deposit->id)->status);
    }

    public function test_the_new_pages_render(): void
    {
        $admin = $this->superAdmin();
        $loan = $this->disbursedLoan();
        foreach ([route('finance.shares.index'), route('finance.shares.create'), route('finance.loans.import'), route('finance.loans.show', $loan),
            route('finance.dividends.create'), route('finance.reports.receipts-payments'), route('finance.reports.savings-dues')] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }
}
