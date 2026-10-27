<?php

namespace SCMS\Finance\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use SCMS\Finance\Enums\EntryStatus;
use SCMS\Finance\Enums\InstallmentStatus;
use SCMS\Finance\Enums\LineComponent;
use SCMS\Finance\Enums\LoanStatus;
use SCMS\Finance\Enums\PenaltyType;
use SCMS\Finance\Events\LoanRepaymentPosted;
use SCMS\Finance\Events\LoanStatusChanged;
use SCMS\Finance\Events\TransactionReversed;
use SCMS\Finance\Exceptions\BranchAccessException;
use SCMS\Finance\Exceptions\FinanceException;
use SCMS\Finance\Exceptions\InvalidStateException;
use SCMS\Finance\Models\CashBankAccount;
use SCMS\Finance\Models\Loan;
use SCMS\Finance\Models\LoanInstallment;
use SCMS\Finance\Models\LoanProduct;
use SCMS\Finance\Models\LoanRepayment;
use SCMS\Finance\Support\Money;
use SCMS\Finance\Support\PenaltyCalculator;
use SCMS\Finance\Support\RepaymentAllocator;

/**
 * Repayments. Principal reduces the receivable; only interest and penalty are income:
 *
 *     Dr Cash/Bank (amount)    Cr Loan receivable (principal)    Cr Interest income    Cr Penalty income
 *
 * The loan row and its unpaid installments are locked while a payment is split, so two
 * cashiers taking money for one loan never pay the same installment twice.
 */
class LoanRepaymentService
{
    public function __construct(
        private readonly JournalService $journal,
        private readonly FinancialYearService $years,
        private readonly TransactionNumberService $numbers,
        private readonly LoanService $loans,
        private readonly RepaymentAllocator $allocator,
        private readonly PenaltyCalculator $penalties,
    ) {
    }

    /** @param  array{amount: string, penalty_amount?: ?string, repayment_date: string, cash_bank_account_id: int, reference?: ?string, description?: ?string}  $data */
    public function repay(Loan $loan, array $data, ?Authenticatable $user): LoanRepayment
    {
        $amount = Money::of($data['amount']);
        $penalty = Money::of($data['penalty_amount'] ?? null);
        if (! $amount->isPositive()) {
            throw new FinanceException(__('The amount must be more than zero.'));
        }
        if ($penalty->isNegative() || $penalty->greaterThan($amount)) {
            throw new FinanceException(__('The penalty must be between zero and the amount paid.'));
        }
        $this->years->assertPostingDate($data['repayment_date']);

        [$repayment, $from, $to] = DB::transaction(function () use ($loan, $data, $amount, $penalty, $user) {
            $loan = $this->loans->lock($loan, $user);
            $from = $loan->status;
            if (! $loan->status->isRunning()) {
                throw new InvalidStateException(__('Repayments are taken on disbursed loans only; this one is :status.', ['status' => $loan->status->label()]));
            }
            if (Carbon::parse($data['repayment_date'])->lt($loan->disbursed_on)) {
                throw new FinanceException(__('The repayment cannot be dated before the disbursement.'));
            }

            $cash = CashBankAccount::query()->withoutBranchScope()->with('account')->findOrFail($data['cash_bank_account_id']);
            if ((int) $cash->branch_id !== (int) $loan->branch_id) {
                throw new BranchAccessException(__('The cash or bank account belongs to another branch.'));
            }

            $installments = LoanInstallment::query()->where('loan_id', $loan->id)->unpaid()
                ->orderBy('installment_no')->lockForUpdate()->get();
            $product = $loan->product()->with(['receivableAccount', 'interestIncomeAccount', 'penaltyIncomeAccount'])->firstOrFail();
            $penaltyParts = $this->penaltyParts($product, $installments, $penalty, $data['repayment_date']);
            $split = $this->allocator->allocate(
                $installments->map(fn (LoanInstallment $i) => ['id' => $i->id, 'principal_remaining' => $i->principalRemaining(), 'interest_remaining' => $i->interestRemaining()]),
                $amount->minus($penalty),
                (array) config('scms-finance.rules.repayment_order', ['interest', 'principal']),
            );
            if ($split['leftover']->isPositive()) {
                throw new FinanceException(__('The payment is :extra more than the loan still owes.', ['extra' => $split['leftover']->format()]));
            }

            $year = $this->years->openYearFor($data['repayment_date']);
            $number = $this->numbers->next('loan_repayment', $data['repayment_date'], (int) $loan->branch_id);

            $repayment = new LoanRepayment(['reference' => $data['reference'] ?? null, 'description' => $data['description'] ?? null]);
            $repayment->forceFill([
                'number' => $number,
                'branch_id' => $loan->branch_id,
                'loan_id' => $loan->id,
                'financial_year_id' => $year->getKey(),
                'repayment_date' => Carbon::parse($data['repayment_date'])->toDateString(),
                'amount' => $amount->toString(),
                'principal_amount' => $split['principal']->toString(),
                'interest_amount' => $split['interest']->toString(),
                'penalty_amount' => $penalty->toString(),
                'cash_bank_account_id' => $cash->id,
                'status' => EntryStatus::Posted->value,
                'created_by' => $user?->getAuthIdentifier(),
                'posted_by' => $user?->getAuthIdentifier(),
                'posted_at' => now(),
            ])->save();

            $byId = $installments->keyBy('id');
            $parts = collect($split['allocations'])->keyBy('id');
            foreach ($installments as $installment) {
                $part = $parts->get($installment->id);
                $penaltyPart = $penaltyParts->get($installment->id, Money::zero());
                if (! $part && ! $penaltyPart->isPositive()) {
                    continue;
                }
                $repayment->allocations()->create([
                    'loan_installment_id' => $installment->id,
                    'principal' => ($part['principal'] ?? Money::zero())->toString(),
                    'interest' => ($part['interest'] ?? Money::zero())->toString(),
                    'penalty' => $penaltyPart->toString(),
                ]);
                if ($part) {
                    $this->apply($byId[$installment->id], $part['principal'], $part['interest'], $data['repayment_date']);
                }
                if ($penaltyPart->isPositive()) {
                    $this->chargePenalty($byId[$installment->id], $penaltyPart);
                }
            }

            $tags = ['loan_id' => $loan->id, 'member_id' => $loan->member_id];
            $lines = [JournalService::debit($cash->account, $amount, ['cash_bank_account_id' => $cash->id, 'member_id' => $loan->member_id, 'component' => LineComponent::Payment])];
            if ($split['principal']->isPositive()) {
                $lines[] = JournalService::credit($product->receivableAccount, $split['principal'], $tags + ['component' => LineComponent::Principal]);
            }
            if ($split['interest']->isPositive()) {
                $lines[] = JournalService::credit($product->interestIncomeAccount, $split['interest'], $tags + ['component' => LineComponent::Interest]);
            }
            if ($penalty->isPositive()) {
                $lines[] = JournalService::credit($product->penaltyIncomeAccount, $penalty, $tags + ['component' => LineComponent::Penalty]);
            }

            $entry = $this->journal->record([
                'branch_id' => $loan->branch_id,
                'entry_date' => $data['repayment_date'],
                'description' => __('Repayment of :loan', ['loan' => $loan->loan_number]).($repayment->description ? ' · '.$repayment->description : ''),
                'reference' => $repayment->reference,
                'source_type' => 'loan_repayment',
                'source_id' => $repayment->id,
                'number' => $number,
            ], $lines, $user);
            $repayment->forceFill(['journal_entry_id' => $entry->id])->save();

            $to = $this->settleStatus($loan, $data['repayment_date']);

            return [$repayment, $from, $to];
        });

        event(new LoanRepaymentPosted($repayment, $user?->getAuthIdentifier()));
        if ($from !== $to) {
            event(new LoanStatusChanged($repayment->loan, $from->value, $to->value, $user?->getAuthIdentifier()));
        }

        return $repayment;
    }

    /**
     * Reverses the loan's latest posted repayment and gives the installments back exactly what it
     * paid. Older repayments wait until the later ones are reversed, so the schedule stays in order.
     */
    public function reverse(LoanRepayment $repayment, string $reason, ?Authenticatable $user): LoanRepayment
    {
        [$repayment, $reversal] = DB::transaction(function () use ($repayment, $reason, $user) {
            $loan = $this->loans->lock($repayment->loan()->firstOrFail(), $user);
            /** @var LoanRepayment $repayment */
            $repayment = LoanRepayment::query()->withoutBranchScope()->lockForUpdate()->findOrFail($repayment->id);

            if ($repayment->status !== EntryStatus::Posted) {
                throw new InvalidStateException(__(':number is :status.', ['number' => $repayment->number, 'status' => $repayment->status->label()]));
            }
            $latest = LoanRepayment::query()->withoutBranchScope()->where('loan_id', $loan->id)->where('status', EntryStatus::Posted->value)
                ->orderByDesc('repayment_date')->orderByDesc('id')->first();
            if ($latest && $latest->id !== $repayment->id) {
                throw new InvalidStateException(__('Reverse the later repayment :number first.', ['number' => $latest->number]));
            }

            $installments = LoanInstallment::query()->where('loan_id', $loan->id)->lockForUpdate()->get()->keyBy('id');
            foreach ($repayment->allocations as $part) {
                $this->apply($installments[$part->loan_installment_id], Money::of($part->principal)->negated(), Money::of($part->interest)->negated(), null);
                if (Money::of($part->penalty)->isPositive()) {
                    $this->chargePenalty($installments[$part->loan_installment_id], Money::of($part->penalty)->negated());
                }
            }

            $reversal = $this->journal->reverse($repayment->journalEntry, $reason, $user);
            $repayment->forceFill(['status' => EntryStatus::Reversed->value, 'reversal_entry_id' => $reversal->id])->save();
            $this->settleStatus($loan, null);

            return [$repayment, $reversal];
        });

        event(new TransactionReversed($repayment, $reversal, $reason, $user?->getAuthIdentifier()));

        return $repayment;
    }

    /** Adds (or with negative amounts, takes back) a payment on an installment. */
    private function apply(LoanInstallment $installment, Money $principal, Money $interest, ?string $date): void
    {
        $principalPaid = $installment->money('principal_paid')->plus($principal);
        $interestPaid = $installment->money('interest_paid')->plus($interest);
        if ($principalPaid->isNegative() || $interestPaid->isNegative()
            || $principalPaid->greaterThan($installment->principal_due) || $interestPaid->greaterThan($installment->interest_due)) {
            throw new FinanceException(__('Installment :n would be paid wrongly; nothing was saved.', ['n' => $installment->installment_no]));
        }

        $installment->forceFill(['principal_paid' => $principalPaid->toString(), 'interest_paid' => $interestPaid->toString()]);
        $paid = $installment->remaining()->isZero();
        $installment->forceFill([
            'status' => ($paid ? InstallmentStatus::Paid : ($installment->totalPaid()->isPositive() ? InstallmentStatus::Partial : InstallmentStatus::Pending))->value,
            'paid_on' => $paid ? ($installment->paid_on?->toDateString() ?? $date) : null,
        ])->save();
    }

    /**
     * The penalty taken, spread over the late installments oldest first. Under a product rule it
     * may not exceed what the rule asks (less may be taken: the rest stays owed); without a rule
     * any penalty is accepted and not tied to an installment.
     *
     * @return Collection<int, Money> installment id => penalty
     */
    private function penaltyParts(LoanProduct $product, Collection $installments, Money $penalty, string $date): Collection
    {
        if (($product->penalty_type ?? PenaltyType::None) === PenaltyType::None || ! $penalty->isPositive()) {
            return collect();
        }

        $owed = $this->penalties->due($product, $installments, Carbon::parse($date));
        $total = Money::sum($owed->all());
        if ($penalty->greaterThan($total)) {
            throw new FinanceException(__('The penalty owed is :owed; :amount is more.', ['owed' => $total->format(), 'amount' => $penalty->format()]));
        }

        $parts = collect();
        $left = $penalty;
        foreach ($owed as $id => $amount) {
            if (! $left->isPositive()) {
                break;
            }
            $parts[$id] = Money::min($amount, $left);
            $left = $left->minus($parts[$id]);
        }

        return $parts;
    }

    /** Adds (or with a negative amount, takes back) penalty charged on an installment. */
    private function chargePenalty(LoanInstallment $installment, Money $amount): void
    {
        $charged = $installment->money('penalty_charged')->plus($amount);
        if ($charged->isNegative()) {
            throw new FinanceException(__('Installment :n would be paid wrongly; nothing was saved.', ['n' => $installment->installment_no]));
        }

        $installment->forceFill(['penalty_charged' => $charged->toString()])->save();
    }

    /** Completed when every installment is paid, else partially paid (active before any payment). */
    private function settleStatus(Loan $loan, ?string $date): LoanStatus
    {
        $this->loans->refreshOutstanding($loan);
        $open = LoanInstallment::query()->where('loan_id', $loan->id)->unpaid()->exists();
        $paidSomething = LoanRepayment::query()->withoutBranchScope()->where('loan_id', $loan->id)->where('status', EntryStatus::Posted->value)->exists();

        $status = ! $open ? LoanStatus::Completed : ($paidSomething ? LoanStatus::PartiallyPaid : LoanStatus::Active);
        $loan->forceFill([
            'status' => $status->value,
            'closed_on' => $status === LoanStatus::Completed ? Carbon::parse($date ?? today())->toDateString() : null,
        ])->save();

        return $status;
    }
}
