<?php

namespace SCMS\Finance\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use SCMS\Finance\Enums\EntryStatus;
use SCMS\Finance\Enums\InstallmentStatus;
use SCMS\Finance\Enums\LineComponent;
use SCMS\Finance\Enums\LoanStatus;
use SCMS\Finance\Events\TransactionReversed;
use SCMS\Finance\Exceptions\BranchAccessException;
use SCMS\Finance\Exceptions\FinanceException;
use SCMS\Finance\Exceptions\InvalidStateException;
use SCMS\Finance\Models\CashBankAccount;
use SCMS\Finance\Models\Loan;
use SCMS\Finance\Models\LoanInstallment;
use SCMS\Finance\Models\LoanProduct;
use SCMS\Finance\Models\LoanRecovery;
use SCMS\Finance\Support\AccountMap;
use SCMS\Finance\Support\LoanScheduleCalculator;
use SCMS\Finance\Support\Money;

/**
 * Changes to loans after they exist: running loans brought in at go-live, rescheduling, interest
 * waived on early settlement, and money recovered after a write-off.
 */
class LoanAdjustmentService
{
    public function __construct(
        private readonly JournalService $journal,
        private readonly LedgerService $ledger,
        private readonly FinancialYearService $years,
        private readonly TransactionNumberService $numbers,
        private readonly SavingsService $savings,
        private readonly LoanService $loans,
        private readonly LoanScheduleCalculator $calculator,
        private readonly AccountMap $accounts,
    ) {
    }

    /**
     * A loan that was already running when the samiti started using the system. What the member
     * still owes is brought in against opening balance equity (no cash moves), and the remaining
     * installments are scheduled from the next due date with the product's terms.
     *
     *     Dr Loan receivable (principal still owed)    Cr Opening balance equity
     *
     * @param  array{branch_id: int, member_id: int, loan_product_id: int, outstanding_principal: string, installments_count: int, next_due_on: string, opened_on: string, original_principal?: ?string, reference?: ?string, purpose?: ?string}  $data
     */
    public function importRunning(array $data, ?Authenticatable $user): Loan
    {
        $branchId = (int) $data['branch_id'];
        $this->journal->assertBranch($user, $branchId);
        $this->savings->assertMemberOfBranch($data['member_id'], $branchId);
        $principal = Money::of($data['outstanding_principal']);
        $count = (int) $data['installments_count'];
        if (! $principal->isPositive() || $count < 1) {
            throw new FinanceException(__('The principal still owed and the number of installments left must be more than zero.'));
        }
        $openedOn = Carbon::parse($data['opened_on'])->startOfDay();
        $firstDue = Carbon::parse($data['next_due_on'])->startOfDay();
        if ($firstDue->lte($openedOn)) {
            throw new FinanceException(__('The next installment must fall due after the go-live date.'));
        }

        return DB::transaction(function () use ($data, $branchId, $principal, $count, $openedOn, $firstDue, $user) {
            $product = LoanProduct::query()->with('receivableAccount')->findOrFail($data['loan_product_id']);
            $year = $this->years->openYearFor($openedOn);
            $schedule = $this->calculator->build($principal, $product->interest_method, (string) $product->interest_rate, $count, $product->repayment_frequency, $firstDue);

            $loan = new Loan(['purpose' => $data['purpose'] ?? null]);
            $loan->forceFill([
                'loan_number' => $this->numbers->next('loan', $openedOn, $branchId),
                'branch_id' => $branchId,
                'member_id' => $data['member_id'],
                'loan_product_id' => $product->id,
                'principal' => $principal->toString(),
                'original_principal' => filled($data['original_principal'] ?? null) ? Money::of($data['original_principal'])->toString() : null,
                'opening_reference' => $data['reference'] ?? null,
                'imported' => true,
                'interest_method' => $product->interest_method->value,
                'interest_rate' => (string) $product->interest_rate,
                'repayment_frequency' => $product->repayment_frequency->value,
                'installments_count' => $count,
                'processing_fee' => '0',
                'total_interest' => $this->calculator->totalInterest($schedule)->toString(),
                'status' => LoanStatus::Active->value,
                'applied_on' => $openedOn->toDateString(),
                'approved_by' => $user?->getAuthIdentifier(),
                'approved_at' => now(),
                'disbursed_on' => $openedOn->toDateString(),
                'first_due_on' => $firstDue->toDateString(),
                'disbursed_by' => $user?->getAuthIdentifier(),
                'financial_year_id' => $year->getKey(),
                'principal_outstanding' => '0',
                'created_by' => $user?->getAuthIdentifier(),
            ])->save();

            foreach ($schedule as $row) {
                $installment = new LoanInstallment();
                $installment->forceFill([
                    'loan_id' => $loan->id, 'installment_no' => $row['installment_no'], 'due_date' => $row['due_date']->toDateString(),
                    'principal_due' => $row['principal']->toString(), 'interest_due' => $row['interest']->toString(), 'status' => InstallmentStatus::Pending->value,
                ])->save();
            }

            $tags = ['loan_id' => $loan->id, 'member_id' => $loan->member_id];
            $entry = $this->journal->record([
                'branch_id' => $branchId,
                'entry_date' => $openedOn,
                'description' => __('Opening balance of loan :number', ['number' => $loan->loan_number]).($loan->opening_reference ? ' · '.$loan->opening_reference : ''),
                'reference' => $loan->opening_reference,
                'source_type' => 'loan',
                'source_id' => $loan->id,
                'number_key' => 'opening_balance',
            ], [
                JournalService::debit($product->receivableAccount, $principal, $tags + ['component' => LineComponent::Principal]),
                JournalService::credit($this->accounts->get('opening_balance_equity'), $principal, $tags + ['component' => LineComponent::Opening]),
            ], $user);

            $loan->forceFill(['disbursement_entry_id' => $entry->id])->save();
            $this->loans->refreshOutstanding($loan);
            \Nit\Master\Support\Activity::log('finance.loan.imported', "Running loan {$loan->loan_number} brought in, {$principal->toString()} owed", $loan);

            return $loan;
        });
    }

    /**
     * Spreads what is still owed (principal and interest, no new interest) over new installments
     * from a new first due date. Installments already paid in part keep what was paid.
     *
     * @param  array{installments_count: int, first_due_on: string, reason: string}  $data
     */
    public function reschedule(Loan $loan, array $data, ?Authenticatable $user): Loan
    {
        $count = (int) $data['installments_count'];
        if ($count < 1 || trim((string) ($data['reason'] ?? '')) === '') {
            throw new FinanceException(__('Give the number of installments and the reason.'));
        }
        $firstDue = Carbon::parse($data['first_due_on'])->startOfDay();
        if ($firstDue->lte(today())) {
            throw new FinanceException(__('The first new installment must fall due after today.'));
        }

        return DB::transaction(function () use ($loan, $count, $firstDue, $data, $user) {
            $loan = $this->loans->lock($loan, $user);
            if (! $loan->status->isRunning()) {
                throw new InvalidStateException(__('Only a running loan can be rescheduled.'));
            }

            $unpaid = LoanInstallment::query()->where('loan_id', $loan->id)->unpaid()->orderBy('installment_no')->lockForUpdate()->get();
            $principal = Money::sum($unpaid->map->principalRemaining()->all());
            $interest = Money::sum($unpaid->map->interestRemaining()->all());
            if (! $principal->plus($interest)->isPositive()) {
                throw new InvalidStateException(__('Nothing is left to reschedule.'));
            }

            // Old unpaid installments keep only what was paid on them (and are closed if anything was).
            foreach ($unpaid as $installment) {
                $hasPayments = $installment->totalPaid()->isPositive() || DB::table('finance_loan_repayment_allocations')->where('loan_installment_id', $installment->id)->exists();
                if (! $hasPayments) {
                    $installment->delete();

                    continue;
                }
                $installment->forceFill([
                    'principal_due' => $installment->principal_paid,
                    'interest_due' => $installment->interest_paid,
                    'status' => InstallmentStatus::Paid->value,
                    'paid_on' => $installment->paid_on?->toDateString() ?? today()->toDateString(),
                ])->save();
            }

            $next = (int) LoanInstallment::query()->where('loan_id', $loan->id)->max('installment_no');
            $principalPart = $principal->dividedDown($count);
            $interestPart = $interest->dividedDown($count);
            $due = $firstDue->copy();
            for ($i = 1; $i <= $count; $i++) {
                $last = $i === $count;
                $installment = new LoanInstallment();
                $installment->forceFill([
                    'loan_id' => $loan->id,
                    'installment_no' => $next + $i,
                    'due_date' => $due->toDateString(),
                    // The last installment takes what the even split rounded away.
                    'principal_due' => ($last ? $principal->minus($principalPart->times($count - 1)) : $principalPart)->toString(),
                    'interest_due' => ($last ? $interest->minus($interestPart->times($count - 1)) : $interestPart)->toString(),
                    'status' => InstallmentStatus::Pending->value,
                ])->save();
                $due = $loan->repayment_frequency->advance($due);
            }

            $loan->forceFill([
                'installments_count' => LoanInstallment::query()->where('loan_id', $loan->id)->count(),
                'rescheduled_at' => now(),
                'reschedule_note' => mb_substr($data['reason'], 0, 500),
            ])->save();
            \Nit\Master\Support\Activity::log('finance.loan.rescheduled', "Loan {$loan->loan_number} rescheduled into {$count} installments: {$data['reason']}", $loan);

            return $loan;
        });
    }

    /**
     * Waives interest still to come (early settlement): the latest installments lose it first.
     * Interest is income only when received, so nothing is posted; the waiver is recorded on the
     * installments.
     */
    public function waiveInterest(Loan $loan, Money|string $amount, string $reason, ?Authenticatable $user): Loan
    {
        $amount = Money::of($amount);
        if (! $amount->isPositive() || trim($reason) === '') {
            throw new FinanceException(__('Give the amount to waive and the reason.'));
        }

        return DB::transaction(function () use ($loan, $amount, $reason, $user) {
            $loan = $this->loans->lock($loan, $user);
            if (! $loan->status->isRunning()) {
                throw new InvalidStateException(__('Interest can be waived on a running loan only.'));
            }

            $unpaid = LoanInstallment::query()->where('loan_id', $loan->id)->unpaid()->orderByDesc('installment_no')->lockForUpdate()->get();
            $open = Money::sum($unpaid->map->interestRemaining()->all());
            if ($amount->greaterThan($open)) {
                throw new FinanceException(__('Only :open of interest is still to come.', ['open' => $open->format()]));
            }

            $left = $amount;
            foreach ($unpaid as $installment) {
                if (! $left->isPositive()) {
                    break;
                }
                $part = Money::min($installment->interestRemaining(), $left);
                if (! $part->isPositive()) {
                    continue;
                }
                $installment->forceFill([
                    'interest_due' => $installment->money('interest_due')->minus($part)->toString(),
                    'interest_waived' => $installment->money('interest_waived')->plus($part)->toString(),
                ]);
                if ($installment->remaining()->isZero()) {
                    $installment->forceFill(['status' => InstallmentStatus::Paid->value, 'paid_on' => today()->toDateString()]);
                }
                $installment->save();
                $left = $left->minus($part);
            }

            if (! LoanInstallment::query()->where('loan_id', $loan->id)->unpaid()->exists()) {
                $loan->forceFill(['status' => LoanStatus::Completed->value, 'closed_on' => today()->toDateString()])->save();
            }
            \Nit\Master\Support\Activity::log('finance.loan.interest_waived', "Interest of {$amount->toString()} waived on loan {$loan->loan_number}: {$reason}", $loan);

            return $loan;
        });
    }

    /**
     * Money received after a write-off is income (the loss was booked when writing off):
     *
     *     Dr Cash/Bank    Cr Recovery of written-off loans
     *
     * @param  array{amount: string, recovery_date: string, cash_bank_account_id: int, reference?: ?string}  $data
     */
    public function recover(Loan $loan, array $data, ?Authenticatable $user): LoanRecovery
    {
        $amount = Money::of($data['amount']);
        if (! $amount->isPositive()) {
            throw new FinanceException(__('The amount must be more than zero.'));
        }
        $this->years->assertPostingDate($data['recovery_date']);

        return DB::transaction(function () use ($loan, $data, $amount, $user) {
            $loan = $this->loans->lock($loan, $user);
            if ($loan->status !== LoanStatus::WrittenOff) {
                throw new InvalidStateException(__('Recoveries are recorded on written-off loans only.'));
            }
            $writtenOff = Money::of(DB::table('finance_journal_lines')->where('journal_entry_id', $loan->write_off_entry_id)->where('component', LineComponent::WriteOff->value)->sum('debit'));
            $recovered = Money::of((string) LoanRecovery::query()->withoutBranchScope()->where('loan_id', $loan->id)->where('status', EntryStatus::Posted->value)->sum('amount'));
            if ($recovered->plus($amount)->greaterThan($writtenOff)) {
                throw new FinanceException(__('Only :left of the written-off :total is left to recover.', ['left' => $writtenOff->minus($recovered)->format(), 'total' => $writtenOff->format()]));
            }

            $cash = CashBankAccount::query()->withoutBranchScope()->with('account')->findOrFail($data['cash_bank_account_id']);
            if ((int) $cash->branch_id !== (int) $loan->branch_id) {
                throw new BranchAccessException(__('The cash or bank account belongs to another branch.'));
            }

            $year = $this->years->openYearFor($data['recovery_date']);
            $number = $this->numbers->next('loan_recovery', $data['recovery_date'], (int) $loan->branch_id);
            $recovery = new LoanRecovery(['reference' => $data['reference'] ?? null]);
            $recovery->forceFill([
                'number' => $number, 'branch_id' => $loan->branch_id, 'loan_id' => $loan->id, 'financial_year_id' => $year->getKey(),
                'recovery_date' => Carbon::parse($data['recovery_date'])->toDateString(), 'amount' => $amount->toString(), 'cash_bank_account_id' => $cash->id,
                'status' => EntryStatus::Posted->value, 'created_by' => $user?->getAuthIdentifier(), 'posted_by' => $user?->getAuthIdentifier(), 'posted_at' => now(),
            ])->save();

            $tags = ['loan_id' => $loan->id, 'member_id' => $loan->member_id, 'component' => LineComponent::Recovery];
            $entry = $this->journal->record([
                'branch_id' => $loan->branch_id,
                'entry_date' => $data['recovery_date'],
                'description' => __('Recovery on written-off loan :number', ['number' => $loan->loan_number]),
                'reference' => $recovery->reference,
                'source_type' => 'loan_recovery',
                'source_id' => $recovery->id,
                'number' => $number,
            ], [
                JournalService::debit($cash->account, $amount, ['cash_bank_account_id' => $cash->id] + $tags),
                JournalService::credit($this->accounts->get('loan_recovery'), $amount, $tags),
            ], $user);
            $recovery->forceFill(['journal_entry_id' => $entry->id])->save();

            return $recovery;
        });
    }

    public function reverseRecovery(LoanRecovery $recovery, string $reason, ?Authenticatable $user): LoanRecovery
    {
        [$recovery, $reversal] = DB::transaction(function () use ($recovery, $reason, $user) {
            $recovery = LoanRecovery::query()->withoutBranchScope()->lockForUpdate()->findOrFail($recovery->id);
            if ($recovery->status !== EntryStatus::Posted) {
                throw new InvalidStateException(__(':number is :status.', ['number' => $recovery->number, 'status' => $recovery->status->label()]));
            }
            $reversal = $this->journal->reverse($recovery->journalEntry, $reason, $user);
            $recovery->forceFill(['status' => EntryStatus::Reversed->value, 'reversal_entry_id' => $reversal->id])->save();

            return [$recovery, $reversal];
        });

        event(new TransactionReversed($recovery, $reversal, $reason, $user?->getAuthIdentifier()));

        return $recovery;
    }
}
