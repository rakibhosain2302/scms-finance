<?php

namespace SCMS\Finance\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use SCMS\Finance\Enums\EntryStatus;
use SCMS\Finance\Enums\InstallmentStatus;
use SCMS\Finance\Enums\LineComponent;
use SCMS\Finance\Enums\LoanStatus;
use SCMS\Finance\Events\LoanDisbursed;
use SCMS\Finance\Events\LoanStatusChanged;
use SCMS\Finance\Events\LoanWrittenOff;
use SCMS\Finance\Events\TransactionReversed;
use SCMS\Finance\Exceptions\BranchAccessException;
use SCMS\Finance\Exceptions\FinanceException;
use SCMS\Finance\Exceptions\InvalidStateException;
use SCMS\Finance\Models\CashBankAccount;
use SCMS\Finance\Models\Loan;
use SCMS\Finance\Models\LoanInstallment;
use SCMS\Finance\Models\LoanProduct;
use SCMS\Finance\Models\LoanRepayment;
use SCMS\Finance\Support\AccountMap;
use SCMS\Finance\Support\LoanScheduleCalculator;
use SCMS\Finance\Support\Money;

/**
 * applied → approved → active (disbursed) → partially_paid → completed.
 *
 * Disbursement is an asset changing form, never an expense:
 *     Dr Loan receivable (principal)    Cr Cash/Bank (principal − fee)    Cr Fee income (fee)
 */
class LoanService
{
    public function __construct(
        private readonly JournalService $journal,
        private readonly LedgerService $ledger,
        private readonly FinancialYearService $years,
        private readonly TransactionNumberService $numbers,
        private readonly SavingsService $savings,
        private readonly LoanScheduleCalculator $calculator,
    ) {
    }

    /** @param  array{branch_id: int, member_id: int, loan_product_id: int, principal: string, installments_count: int, applied_on: string, purpose?: ?string, collateral?: ?string, guarantors?: list<array<string, mixed>>}  $data */
    public function apply(array $data, ?Authenticatable $user): Loan
    {
        $branchId = (int) $data['branch_id'];
        $this->journal->assertBranch($user, $branchId);
        $this->savings->assertMemberOfBranch($data['member_id'], $branchId);

        $product = LoanProduct::query()->findOrFail($data['loan_product_id']);
        $principal = Money::of($data['principal']);
        $count = (int) $data['installments_count'];
        $this->assertTerms($product, $principal, $count);
        $guarantors = array_values(array_filter($data['guarantors'] ?? [], fn ($g) => filled($g['name'] ?? null)));
        if (count($guarantors) < (int) $product->guarantors_required) {
            throw new FinanceException(__(':name needs :n guarantors.', ['name' => $product->name, 'n' => $product->guarantors_required]));
        }

        // The terms are frozen on the loan; the interest is recomputed with the real first due date at disbursement.
        $preview = $this->calculator->build($principal, $product->interest_method, (string) $product->interest_rate, $count,
            $product->repayment_frequency, $product->repayment_frequency->advance(Carbon::parse($data['applied_on'])));

        return DB::transaction(function () use ($data, $branchId, $product, $principal, $count, $preview, $user, $guarantors) {
            $loan = new Loan(['purpose' => $data['purpose'] ?? null, 'collateral' => $data['collateral'] ?? null]);
            $loan->forceFill([
                'loan_number' => $this->numbers->next('loan', $data['applied_on'], $branchId),
                'branch_id' => $branchId,
                'member_id' => $data['member_id'],
                'loan_product_id' => $product->id,
                'principal' => $principal->toString(),
                'interest_method' => $product->interest_method->value,
                'interest_rate' => (string) $product->interest_rate,
                'repayment_frequency' => $product->repayment_frequency->value,
                'installments_count' => $count,
                'processing_fee' => $this->processingFee($product, $principal)->toString(),
                'total_interest' => $this->calculator->totalInterest($preview)->toString(),
                'status' => LoanStatus::Applied->value,
                'applied_on' => Carbon::parse($data['applied_on'])->toDateString(),
                'principal_outstanding' => '0',
                'created_by' => $user?->getAuthIdentifier(),
            ])->save();

            foreach ($guarantors as $guarantor) {
                $loan->guarantors()->create(array_intersect_key($guarantor, array_flip(['member_id', 'name', 'relation', 'phone', 'nid'])));
            }

            return $loan;
        });
    }

    /** Maker-checker: nobody approves their own application (super admins excepted, see rules.loan_self_approval). */
    public function approve(Loan $loan, ?Authenticatable $user, ?string $note = null): Loan
    {
        return $this->decide($loan, $user, LoanStatus::Approved, $note, function (Loan $loan) use ($user) {
            if ($user && ! config('scms-finance.rules.loan_self_approval', false)
                && (string) $loan->created_by === (string) $user->getAuthIdentifier()
                && ! (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin())) {
                throw new FinanceException(__('Someone other than the person who entered the application must approve it.'));
            }

            return ['approved_by' => $user?->getAuthIdentifier(), 'approved_at' => now()];
        });
    }

    public function reject(Loan $loan, ?Authenticatable $user, string $note): Loan
    {
        if (trim($note) === '') {
            throw new FinanceException(__('Give the reason for the rejection.'));
        }

        return $this->decide($loan, $user, LoanStatus::Rejected, $note, fn () => ['rejected_by' => $user?->getAuthIdentifier(), 'rejected_at' => now()]);
    }

    public function cancel(Loan $loan, ?Authenticatable $user, ?string $note = null): Loan
    {
        return $this->decide($loan, $user, LoanStatus::Cancelled, $note, fn () => []);
    }

    /**
     * Pays the loan out and writes its schedule.
     *
     * @param  array{disbursed_on: string, first_due_on: string, cash_bank_account_id: int}  $data
     */
    public function disburse(Loan $loan, array $data, ?Authenticatable $user): Loan
    {
        $this->years->assertPostingDate($data['disbursed_on']);
        $disbursedOn = Carbon::parse($data['disbursed_on'])->startOfDay();
        $firstDue = Carbon::parse($data['first_due_on'])->startOfDay();
        if ($firstDue->lte($disbursedOn)) {
            throw new FinanceException(__('The first installment must fall due after the disbursement.'));
        }

        $loan = DB::transaction(function () use ($loan, $data, $disbursedOn, $firstDue, $user) {
            $loan = $this->lock($loan, $user);
            if ($loan->status !== LoanStatus::Approved) {
                throw new InvalidStateException(__('Only an approved loan can be disbursed; this one is :status.', ['status' => $loan->status->label()]));
            }
            if ($disbursedOn->lt($loan->applied_on)) {
                throw new FinanceException(__('The loan cannot be paid out before it was applied for.'));
            }

            $cash = CashBankAccount::query()->withoutBranchScope()->with('account')->findOrFail($data['cash_bank_account_id']);
            if ((int) $cash->branch_id !== (int) $loan->branch_id) {
                throw new BranchAccessException(__('The cash or bank account belongs to another branch.'));
            }

            $product = $loan->product()->with(['receivableAccount', 'feeIncomeAccount'])->firstOrFail();
            $principal = $loan->money('principal');
            $fee = $loan->money('processing_fee');
            $year = $this->years->openYearFor($disbursedOn);

            $schedule = $this->calculator->build($principal, $loan->interest_method, (string) $loan->interest_rate,
                (int) $loan->installments_count, $loan->repayment_frequency, $firstDue);
            foreach ($schedule as $row) {
                $installment = new LoanInstallment();
                $installment->forceFill([
                    'loan_id' => $loan->id,
                    'installment_no' => $row['installment_no'],
                    'due_date' => $row['due_date']->toDateString(),
                    'principal_due' => $row['principal']->toString(),
                    'interest_due' => $row['interest']->toString(),
                    'status' => InstallmentStatus::Pending->value,
                ])->save();
            }

            $tags = ['loan_id' => $loan->id, 'member_id' => $loan->member_id];
            $lines = [
                JournalService::debit($product->receivableAccount, $principal, $tags + ['component' => LineComponent::Principal]),
                JournalService::credit($cash->account, $principal->minus($fee), ['cash_bank_account_id' => $cash->id, 'member_id' => $loan->member_id, 'component' => LineComponent::Payment]),
            ];
            if ($fee->isPositive()) {
                $lines[] = JournalService::credit($product->feeIncomeAccount, $fee, $tags + ['component' => LineComponent::Fee, 'description' => __('Processing fee')]);
            }

            $entry = $this->journal->record([
                'branch_id' => $loan->branch_id,
                'entry_date' => $disbursedOn,
                'description' => __('Loan disbursement :number', ['number' => $loan->loan_number]),
                'reference' => $loan->loan_number,
                'source_type' => 'loan',
                'source_id' => $loan->id,
                'number_key' => 'loan_disbursement',
            ], $lines, $user);

            $loan->forceFill([
                'status' => LoanStatus::Active->value,
                'disbursed_on' => $disbursedOn->toDateString(),
                'first_due_on' => $firstDue->toDateString(),
                'disbursed_by' => $user?->getAuthIdentifier(),
                'disbursement_account_id' => $cash->id,
                'disbursement_entry_id' => $entry->id,
                'financial_year_id' => $year->getKey(),
                'total_interest' => $this->calculator->totalInterest($schedule)->toString(),
            ])->save();
            $this->refreshOutstanding($loan);

            return $loan;
        });

        event(new LoanDisbursed($loan, $user?->getAuthIdentifier()));
        event(new LoanStatusChanged($loan, LoanStatus::Approved->value, LoanStatus::Active->value, $user?->getAuthIdentifier()));

        return $loan;
    }

    /** Undoes a disbursement made in error. Only before any repayment; the loan ends cancelled. */
    public function reverseDisbursement(Loan $loan, string $reason, ?Authenticatable $user): Loan
    {
        [$loan, $reversal] = DB::transaction(function () use ($loan, $reason, $user) {
            $loan = $this->lock($loan, $user);
            if ($loan->status !== LoanStatus::Active) {
                throw new InvalidStateException(__('Only a disbursed loan without repayments can have its disbursement reversed.'));
            }
            if (LoanRepayment::query()->withoutBranchScope()->where('loan_id', $loan->id)->where('status', EntryStatus::Posted->value)->exists()) {
                throw new InvalidStateException(__('Reverse the repayments first.'));
            }

            $reversal = $this->journal->reverse($loan->disbursementEntry, $reason, $user);
            $loan->forceFill(['status' => LoanStatus::Cancelled->value, 'decision_note' => mb_substr($reason, 0, 500), 'closed_on' => today()->toDateString()])->save();
            $this->refreshOutstanding($loan);

            return [$loan, $reversal];
        });

        event(new TransactionReversed($loan, $reversal, $reason, $user?->getAuthIdentifier()));

        return $loan;
    }

    /**
     * Writes off what a member will not repay: the principal still owed leaves the receivable and
     * becomes an expense. Interest never received was never income, so nothing else moves.
     *
     *     Dr Loan loss (expense)    Cr Loan receivable (principal)
     *
     * @param  array{written_off_on: string, reason: string}  $data
     */
    public function writeOff(Loan $loan, array $data, ?Authenticatable $user): Loan
    {
        if (trim((string) ($data['reason'] ?? '')) === '') {
            throw new FinanceException(__('Give the reason for the write-off.'));
        }
        $this->years->assertPostingDate($data['written_off_on']);

        [$loan, $from] = DB::transaction(function () use ($loan, $data, $user) {
            $loan = $this->lock($loan, $user);
            $from = $loan->status;
            if (! $loan->status->isRunning()) {
                throw new InvalidStateException(__('Only a running loan can be written off; this one is :status.', ['status' => $loan->status->label()]));
            }
            $date = Carbon::parse($data['written_off_on'])->startOfDay();
            if ($date->lt($loan->disbursed_on)) {
                throw new FinanceException(__('The write-off cannot be dated before the disbursement.'));
            }

            $outstanding = $this->ledger->loanOutstanding($loan->id);
            if (! $outstanding->isPositive()) {
                throw new InvalidStateException(__('Nothing of the principal is owed.'));
            }

            $product = $loan->product()->with('receivableAccount')->firstOrFail();
            $tags = ['loan_id' => $loan->id, 'member_id' => $loan->member_id];
            $entry = $this->journal->record([
                'branch_id' => $loan->branch_id,
                'entry_date' => $date,
                'description' => __('Write-off of :number: :reason', ['number' => $loan->loan_number, 'reason' => $data['reason']]),
                'reference' => $loan->loan_number,
                'source_type' => 'loan_write_off',
                'source_id' => $loan->id,
                'number_key' => 'loan_write_off',
            ], [
                JournalService::debit(app(AccountMap::class)->get('loan_write_off'), $outstanding, $tags + ['component' => LineComponent::WriteOff]),
                JournalService::credit($product->receivableAccount, $outstanding, $tags + ['component' => LineComponent::Principal]),
            ], $user);

            $loan->forceFill([
                'status' => LoanStatus::WrittenOff->value,
                'written_off_on' => $date->toDateString(),
                'written_off_by' => $user?->getAuthIdentifier(),
                'write_off_entry_id' => $entry->id,
                'write_off_reason' => mb_substr($data['reason'], 0, 500),
                'closed_on' => $date->toDateString(),
            ])->save();
            $this->refreshOutstanding($loan);

            return [$loan, $from];
        });

        event(new LoanWrittenOff($loan, $user?->getAuthIdentifier()));
        event(new LoanStatusChanged($loan, $from->value, LoanStatus::WrittenOff->value, $user?->getAuthIdentifier()));

        return $loan;
    }

    /** The schedule the application would get (for the form's preview). */
    public function preview(LoanProduct $product, Money|string $principal, int $count, Carbon $firstDue): array
    {
        $principal = Money::of($principal);
        $this->assertTerms($product, $principal, $count);

        return $this->calculator->build($principal, $product->interest_method, (string) $product->interest_rate, $count, $product->repayment_frequency, $firstDue);
    }

    /** Recomputes the cached outstanding principal from the ledger (call with the loan locked). */
    public function refreshOutstanding(Loan $loan): void
    {
        $loan->forceFill(['principal_outstanding' => $this->ledger->loanOutstanding($loan->id)->toString()])->save();
    }

    public function lock(Loan $loan, ?Authenticatable $user): Loan
    {
        /** @var Loan $loan */
        $loan = Loan::query()->withoutBranchScope()->lockForUpdate()->findOrFail($loan->id);
        $this->journal->assertBranch($user, $loan->branch_id);

        return $loan;
    }

    private function assertTerms(LoanProduct $product, Money $principal, int $count): void
    {
        if (! $product->is_active) {
            throw new FinanceException(__('The loan product :name is turned off.', ['name' => $product->name]));
        }
        if (! $principal->isPositive() || $principal->lessThan($product->min_amount)
            || ($product->max_amount !== null && $principal->greaterThan($product->max_amount))) {
            throw new FinanceException(__(':name lends from :min to :max.', [
                'name' => $product->name,
                'min' => Money::of($product->min_amount)->format(),
                'max' => $product->max_amount !== null ? Money::of($product->max_amount)->format() : __('any amount'),
            ]));
        }
        if ($count < $product->min_installments || $count > $product->max_installments) {
            throw new FinanceException(__(':name is repaid in :min to :max installments.', ['name' => $product->name, 'min' => $product->min_installments, 'max' => $product->max_installments]));
        }
    }

    private function processingFee(LoanProduct $product, Money $principal): Money
    {
        return $principal->times((string) $product->processing_fee_rate)->dividedBy(100);
    }

    private function decide(Loan $loan, ?Authenticatable $user, LoanStatus $to, ?string $note, callable $fields): Loan
    {
        $allowed = [
            LoanStatus::Approved->value => [LoanStatus::Applied],
            LoanStatus::Rejected->value => [LoanStatus::Applied],
            LoanStatus::Cancelled->value => [LoanStatus::Applied, LoanStatus::Approved],
        ][$to->value];

        [$loan, $from] = DB::transaction(function () use ($loan, $user, $to, $note, $fields, $allowed) {
            $loan = $this->lock($loan, $user);
            $from = $loan->status;
            if (! in_array($from, $allowed, true)) {
                throw new InvalidStateException(__('A loan that is :status cannot become :to.', ['status' => $from->label(), 'to' => $to->label()]));
            }

            $loan->forceFill(['status' => $to->value, 'decision_note' => $note !== null ? mb_substr($note, 0, 500) : $loan->decision_note] + $fields($loan))->save();

            return [$loan, $from];
        });

        event(new LoanStatusChanged($loan, $from->value, $to->value, $user?->getAuthIdentifier()));

        return $loan;
    }
}
