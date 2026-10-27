<?php

namespace SCMS\Finance\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use SCMS\Finance\Enums\EntryStatus;
use SCMS\Finance\Enums\LineComponent;
use SCMS\Finance\Events\TransactionReversed;
use SCMS\Finance\Events\TransferPosted;
use SCMS\Finance\Exceptions\BranchAccessException;
use SCMS\Finance\Exceptions\FinanceException;
use SCMS\Finance\Exceptions\InvalidStateException;
use SCMS\Finance\Models\CashBankAccount;
use SCMS\Finance\Models\Transfer;
use SCMS\Finance\Support\MakerChecker;
use SCMS\Finance\Support\Money;

/**
 * Cash → bank, bank → cash, bank → bank, cash → cash inside one branch:
 *     Dr destination    Cr source
 * Never income or expense.
 */
class TransferService
{
    public function __construct(
        private readonly JournalService $journal,
        private readonly FinancialYearService $years,
        private readonly TransactionNumberService $numbers,
    ) {
    }

    /** @param  array{branch_id: int, transfer_date: string, from_account_id: int, to_account_id: int, amount: string}  $data */
    public function create(array $data, bool $post, ?Authenticatable $user): Transfer
    {
        $branchId = (int) $data['branch_id'];
        $this->journal->assertBranch($user, $branchId);

        $transfer = DB::transaction(function () use ($data, $branchId, $user) {
            $transfer = new Transfer(Arr::only($data, (new Transfer())->getFillable()));
            $this->check($transfer, $branchId);
            $transfer->forceFill([
                'number' => $this->numbers->next('transfer', $data['transfer_date'], $branchId),
                'branch_id' => $branchId,
                'financial_year_id' => $this->years->openYearFor($data['transfer_date'])->getKey(),
                'status' => EntryStatus::Draft->value,
                'created_by' => $user?->getAuthIdentifier(),
            ])->save();

            return $transfer;
        });

        return $post ? $this->post($transfer, $user) : $transfer;
    }

    public function update(Transfer $transfer, array $data, ?Authenticatable $user): Transfer
    {
        return DB::transaction(function () use ($transfer, $data, $user) {
            $transfer = $this->lockDraft($transfer, $user);
            $transfer->fill(Arr::only($data, $transfer->getFillable()));
            $this->check($transfer, (int) $transfer->branch_id);
            $transfer->forceFill(['financial_year_id' => $this->years->openYearFor($transfer->transfer_date)->getKey()])->save();

            return $transfer;
        });
    }

    public function post(Transfer $transfer, ?Authenticatable $user): Transfer
    {
        $transfer = DB::transaction(function () use ($transfer, $user) {
            $transfer = $this->lockDraft($transfer, $user);
            MakerChecker::assertMayPost($user, $transfer);
            [$from, $to] = $this->check($transfer, (int) $transfer->branch_id);
            $tag = fn (CashBankAccount $a) => ['cash_bank_account_id' => $a->id, 'component' => LineComponent::Transfer];

            $entry = $this->journal->record([
                'branch_id' => $transfer->branch_id,
                'entry_date' => $transfer->transfer_date,
                'description' => $transfer->description ?: __(':from to :to', ['from' => $from->name, 'to' => $to->name]),
                'reference' => $transfer->reference,
                'source_type' => 'transfer',
                'source_id' => $transfer->id,
                'number' => $transfer->number,
            ], [
                JournalService::debit($to->account, $transfer->amount, $tag($to)),
                JournalService::credit($from->account, $transfer->amount, $tag($from)),
            ], $user);

            $transfer->forceFill([
                'journal_entry_id' => $entry->id,
                'financial_year_id' => $entry->financial_year_id,
                'status' => EntryStatus::Posted->value,
                'posted_by' => $user?->getAuthIdentifier(),
                'posted_at' => now(),
            ])->save();

            return $transfer;
        });

        event(new TransferPosted($transfer, $user?->getAuthIdentifier()));

        return $transfer;
    }

    public function cancel(Transfer $transfer, ?Authenticatable $user): Transfer
    {
        return DB::transaction(function () use ($transfer, $user) {
            $transfer = $this->lockDraft($transfer, $user);
            $transfer->forceFill(['status' => EntryStatus::Cancelled->value])->save();

            return $transfer;
        });
    }

    public function reverse(Transfer $transfer, string $reason, ?Authenticatable $user): Transfer
    {
        [$transfer, $reversal] = DB::transaction(function () use ($transfer, $reason, $user) {
            /** @var Transfer $transfer */
            $transfer = Transfer::query()->withoutBranchScope()->lockForUpdate()->findOrFail($transfer->id);
            $this->journal->assertBranch($user, $transfer->branch_id);
            if ($transfer->status !== EntryStatus::Posted) {
                throw new InvalidStateException(__(':number is :status.', ['number' => $transfer->number, 'status' => $transfer->status->label()]));
            }

            $reversal = $this->journal->reverse($transfer->journalEntry, $reason, $user);
            $transfer->forceFill(['status' => EntryStatus::Reversed->value, 'reversal_entry_id' => $reversal->id])->save();

            return [$transfer, $reversal];
        });

        event(new TransactionReversed($transfer, $reversal, $reason, $user?->getAuthIdentifier()));

        return $transfer;
    }

    /** @return array{0: CashBankAccount, 1: CashBankAccount} */
    private function check(Transfer $transfer, int $branchId): array
    {
        if (! Money::of($transfer->amount)->isPositive()) {
            throw new FinanceException(__('The amount must be more than zero.'));
        }
        if ((int) $transfer->from_account_id === (int) $transfer->to_account_id) {
            throw new FinanceException(__('Choose two different accounts.'));
        }
        $this->years->assertPostingDate($transfer->transfer_date);

        $accounts = CashBankAccount::query()->withoutBranchScope()->with('account')
            ->whereKey([$transfer->from_account_id, $transfer->to_account_id])->get()->keyBy('id');
        $from = $accounts[$transfer->from_account_id] ?? throw new FinanceException(__('Choose the account the money leaves.'));
        $to = $accounts[$transfer->to_account_id] ?? throw new FinanceException(__('Choose the account the money goes to.'));

        foreach ([$from, $to] as $account) {
            if ((int) $account->branch_id !== $branchId) {
                throw new BranchAccessException(__('Both accounts must belong to the branch of the transfer.'));
            }
            if (! $account->is_active) {
                throw new FinanceException(__(':name is turned off.', ['name' => $account->name]));
            }
        }

        return [$from, $to];
    }

    private function lockDraft(Transfer $transfer, ?Authenticatable $user): Transfer
    {
        /** @var Transfer $transfer */
        $transfer = Transfer::query()->withoutBranchScope()->lockForUpdate()->findOrFail($transfer->id);
        $this->journal->assertBranch($user, $transfer->branch_id);
        if ($transfer->status !== EntryStatus::Draft) {
            throw new InvalidStateException(__(':number is :status; only drafts can change.', ['number' => $transfer->number, 'status' => $transfer->status->label()]));
        }

        return $transfer;
    }
}
