<?php

namespace SCMS\Finance\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use SCMS\Finance\Enums\EntryStatus;
use SCMS\Finance\Enums\LineComponent;
use SCMS\Finance\Events\TransactionReversed;
use SCMS\Finance\Exceptions\BranchAccessException;
use SCMS\Finance\Exceptions\FinanceException;
use SCMS\Finance\Exceptions\InsufficientBalanceException;
use SCMS\Finance\Exceptions\InvalidStateException;
use SCMS\Finance\Models\CashBankAccount;
use SCMS\Finance\Models\ShareTransaction;
use SCMS\Finance\Support\AccountMap;
use SCMS\Finance\Support\Money;

/**
 * Share capital of members. It is equity of the samiti, never income:
 *
 *     purchase  Dr Cash/Bank       Cr Share capital (member tagged)
 *     refund    Dr Share capital   Cr Cash/Bank
 *
 * A member's share balance is the credit balance of the share capital account on their lines.
 */
class ShareService
{
    public function __construct(
        private readonly JournalService $journal,
        private readonly LedgerService $ledger,
        private readonly FinancialYearService $years,
        private readonly TransactionNumberService $numbers,
        private readonly SavingsService $savings,
        private readonly AccountMap $accounts,
    ) {
    }

    public function balance(int $memberId): Money
    {
        return $this->ledger->net($this->ledger->lines()->where('l.member_id', $memberId)
            ->where('l.account_id', $this->accounts->get('share_capital')->id))->negated();
    }

    /** @param  array{branch_id: int, member_id: int, type: string, amount: string, shares?: ?int, transaction_date: string, cash_bank_account_id: int, reference?: ?string, description?: ?string}  $data */
    public function post(array $data, ?Authenticatable $user): ShareTransaction
    {
        $amount = Money::of($data['amount']);
        if (! $amount->isPositive()) {
            throw new FinanceException(__('The amount must be more than zero.'));
        }
        $type = $data['type'] === ShareTransaction::REFUND ? ShareTransaction::REFUND : ShareTransaction::PURCHASE;
        $branchId = (int) $data['branch_id'];
        $this->journal->assertBranch($user, $branchId);
        $this->savings->assertMemberOfBranch($data['member_id'], $branchId);
        $this->years->assertPostingDate($data['transaction_date']);

        $price = config('scms-finance.share_price');
        $shares = $data['shares'] ?? null;
        if ($price && Money::of((string) $price)->isPositive()) {
            $shares = (int) $amount->dividedBy((string) $price)->toString();
            if (! Money::of((string) $price)->times($shares)->equals($amount)) {
                throw new FinanceException(__('One share costs :price; the amount must be a whole number of shares.', ['price' => Money::of((string) $price)->format()]));
            }
        }

        return tap(DB::transaction(function () use ($data, $type, $amount, $shares, $branchId, $user) {
            $cash = CashBankAccount::query()->withoutBranchScope()->with('account')->findOrFail($data['cash_bank_account_id']);
            if ((int) $cash->branch_id !== $branchId) {
                throw new BranchAccessException(__('The cash or bank account belongs to another branch.'));
            }
            // Refunds of one member run one after the other: the member's earlier share rows are locked.
            ShareTransaction::query()->withoutBranchScope()->where('member_id', $data['member_id'])->lockForUpdate()->get(['id']);
            if ($type === ShareTransaction::REFUND && $this->balance((int) $data['member_id'])->lessThan($amount)) {
                throw new InsufficientBalanceException(__('The member holds shares of :balance only.', ['balance' => $this->balance((int) $data['member_id'])->format()]));
            }

            $year = $this->years->openYearFor($data['transaction_date']);
            $number = $this->numbers->next('share_'.$type, $data['transaction_date'], $branchId);
            $transaction = new ShareTransaction(['reference' => $data['reference'] ?? null, 'description' => $data['description'] ?? null]);
            $transaction->forceFill([
                'number' => $number,
                'branch_id' => $branchId,
                'member_id' => $data['member_id'],
                'financial_year_id' => $year->getKey(),
                'type' => $type,
                'transaction_date' => Carbon::parse($data['transaction_date'])->toDateString(),
                'shares' => $shares,
                'amount' => $amount->toString(),
                'cash_bank_account_id' => $cash->id,
                'status' => EntryStatus::Posted->value,
                'created_by' => $user?->getAuthIdentifier(),
                'posted_by' => $user?->getAuthIdentifier(),
                'posted_at' => now(),
            ])->save();

            $capital = $this->accounts->get('share_capital');
            $tags = ['member_id' => $data['member_id'], 'component' => LineComponent::Share];
            $cashLine = ['cash_bank_account_id' => $cash->id] + $tags;
            $lines = $type === ShareTransaction::PURCHASE
                ? [JournalService::debit($cash->account, $amount, $cashLine), JournalService::credit($capital, $amount, $tags)]
                : [JournalService::debit($capital, $amount, $tags), JournalService::credit($cash->account, $amount, $cashLine)];

            $entry = $this->journal->record([
                'branch_id' => $branchId,
                'entry_date' => $data['transaction_date'],
                'description' => $transaction->typeLabel().($transaction->description ? ' · '.$transaction->description : ''),
                'reference' => $transaction->reference,
                'source_type' => 'share_transaction',
                'source_id' => $transaction->id,
                'number' => $number,
            ], $lines, $user);
            $transaction->forceFill(['journal_entry_id' => $entry->id])->save();

            return $transaction;
        }), fn (ShareTransaction $t) => \Nit\Master\Support\Activity::log('finance.share.'.$t->type, "{$t->typeLabel()} {$t->number} of {$t->amount}", $t));
    }

    public function reverse(ShareTransaction $transaction, string $reason, ?Authenticatable $user): ShareTransaction
    {
        [$transaction, $reversal] = DB::transaction(function () use ($transaction, $reason, $user) {
            $transaction = ShareTransaction::query()->withoutBranchScope()->lockForUpdate()->findOrFail($transaction->id);
            $this->journal->assertBranch($user, $transaction->branch_id);
            if ($transaction->status !== EntryStatus::Posted) {
                throw new InvalidStateException(__(':number is :status.', ['number' => $transaction->number, 'status' => $transaction->status->label()]));
            }
            if ($transaction->type === ShareTransaction::PURCHASE && $this->balance((int) $transaction->member_id)->lessThan($transaction->amount)) {
                throw new InsufficientBalanceException(__('Those shares were already refunded; the balance would go below zero.'));
            }

            $reversal = $this->journal->reverse($transaction->journalEntry, $reason, $user);
            $transaction->forceFill(['status' => EntryStatus::Reversed->value, 'reversal_entry_id' => $reversal->id])->save();

            return [$transaction, $reversal];
        });

        event(new TransactionReversed($transaction, $reversal, $reason, $user?->getAuthIdentifier()));

        return $transaction;
    }
}
