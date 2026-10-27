<?php

namespace SCMS\Finance\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use SCMS\Finance\Enums\EntryStatus;
use SCMS\Finance\Enums\LineComponent;
use SCMS\Finance\Enums\SavingsTransactionType;
use SCMS\Finance\Events\DepositPosted;
use SCMS\Finance\Events\TransactionReversed;
use SCMS\Finance\Events\WithdrawalPosted;
use SCMS\Finance\Exceptions\BranchAccessException;
use SCMS\Finance\Exceptions\FinanceException;
use SCMS\Finance\Exceptions\InsufficientBalanceException;
use SCMS\Finance\Exceptions\InvalidStateException;
use SCMS\Finance\Models\CashBankAccount;
use SCMS\Finance\Models\SavingsAccount;
use SCMS\Finance\Models\SavingsTransaction;
use SCMS\Finance\Support\Money;

/**
 * Deposits and withdrawals. A member's savings are a liability (the samiti owes them), never income:
 *
 *     deposit     Dr Cash/Bank            Cr Member savings (sub-ledger: the savings account)
 *     withdrawal  Dr Member savings       Cr Cash/Bank
 *
 * The savings account row is locked for the whole posting, so two withdrawals from one account
 * run one after the other and the second sees the balance the first left.
 */
class SavingsTransactionService
{
    public function __construct(
        private readonly JournalService $journal,
        private readonly LedgerService $ledger,
        private readonly FinancialYearService $years,
        private readonly TransactionNumberService $numbers,
    ) {
    }

    /** @param  array{amount: string, transaction_date: string, cash_bank_account_id: int, reference?: ?string, description?: ?string}  $data */
    public function deposit(SavingsAccount $account, array $data, ?Authenticatable $user): SavingsTransaction
    {
        $transaction = $this->post(SavingsTransactionType::Deposit, $account, $data, $user);
        event(new DepositPosted($transaction, $user?->getAuthIdentifier()));

        return $transaction;
    }

    /** @param  array{amount: string, transaction_date: string, cash_bank_account_id: int, reference?: ?string, description?: ?string}  $data */
    public function withdraw(SavingsAccount $account, array $data, ?Authenticatable $user): SavingsTransaction
    {
        $transaction = $this->post(SavingsTransactionType::Withdrawal, $account, $data, $user);
        event(new WithdrawalPosted($transaction, $user?->getAuthIdentifier()));

        return $transaction;
    }

    public function reverse(SavingsTransaction $transaction, string $reason, ?Authenticatable $user): SavingsTransaction
    {
        [$transaction, $reversal] = DB::transaction(function () use ($transaction, $reason, $user) {
            $account = $this->lockAccount($transaction->savings_account_id, $user);
            /** @var SavingsTransaction $transaction */
            $transaction = SavingsTransaction::query()->withoutBranchScope()->lockForUpdate()->findOrFail($transaction->id);

            if ($transaction->status !== EntryStatus::Posted) {
                throw new InvalidStateException(__(':number is :status.', ['number' => $transaction->number, 'status' => $transaction->status->label()]));
            }
            if ($transaction->type === SavingsTransactionType::Deposit
                && $this->ledger->savingsBalance($account->id)->lessThan($transaction->amount)) {
                throw new InsufficientBalanceException(__('The member has already withdrawn this deposit; the balance would go below zero.'));
            }

            $reversal = $this->journal->reverse($transaction->journalEntry, $reason, $user);
            $transaction->forceFill(['status' => EntryStatus::Reversed->value, 'reversal_entry_id' => $reversal->id])->save();
            $this->refreshBalance($account);

            return [$transaction, $reversal];
        });

        event(new TransactionReversed($transaction, $reversal, $reason, $user?->getAuthIdentifier()));

        return $transaction;
    }

    /** Recomputes the cached balance from the ledger (call with the account row locked). */
    public function refreshBalance(SavingsAccount $account): void
    {
        $account->forceFill(['balance' => $this->ledger->savingsBalance($account->id)->toString()])->save();
    }

    private function post(SavingsTransactionType $type, SavingsAccount $account, array $data, ?Authenticatable $user): SavingsTransaction
    {
        $amount = Money::of($data['amount']);
        if (! $amount->isPositive()) {
            throw new FinanceException(__('The amount must be more than zero.'));
        }
        $this->years->assertPostingDate($data['transaction_date']);

        return DB::transaction(function () use ($type, $account, $data, $amount, $user) {
            $account = $this->lockAccount($account->id, $user);
            $scheme = $account->scheme;

            if (! $account->isActive()) {
                throw new InvalidStateException(__('The account is :status.', ['status' => $account->status->label()]));
            }
            if (Carbon::parse($data['transaction_date'])->lt($account->opened_on)) {
                throw new FinanceException(__('The date is before the account was opened.'));
            }

            if ($type === SavingsTransactionType::Deposit) {
                if ($amount->lessThan($scheme->min_deposit)) {
                    throw new FinanceException(__('The smallest deposit for :scheme is :amount.', ['scheme' => $scheme->name, 'amount' => Money::of($scheme->min_deposit)->format()]));
                }
                if ($scheme->max_deposit !== null && $amount->greaterThan($scheme->max_deposit)) {
                    throw new FinanceException(__('The largest deposit for :scheme is :amount.', ['scheme' => $scheme->name, 'amount' => Money::of($scheme->max_deposit)->format()]));
                }
            } else {
                $this->assertCanWithdraw($account, $amount, $data['transaction_date']);
            }

            $cash = CashBankAccount::query()->withoutBranchScope()->with('account')->findOrFail($data['cash_bank_account_id']);
            if ((int) $cash->branch_id !== (int) $account->branch_id) {
                throw new BranchAccessException(__('The cash or bank account belongs to another branch.'));
            }

            $year = $this->years->openYearFor($data['transaction_date']);
            $number = $this->numbers->next($type->numberKey(), $data['transaction_date'], (int) $account->branch_id);

            $transaction = new SavingsTransaction(['reference' => $data['reference'] ?? null, 'description' => $data['description'] ?? null]);
            $transaction->forceFill([
                'number' => $number,
                'branch_id' => $account->branch_id,
                'savings_account_id' => $account->id,
                'financial_year_id' => $year->getKey(),
                'type' => $type->value,
                'transaction_date' => Carbon::parse($data['transaction_date'])->toDateString(),
                'amount' => $amount->toString(),
                'cash_bank_account_id' => $cash->id,
                'status' => EntryStatus::Posted->value,
                'created_by' => $user?->getAuthIdentifier(),
                'posted_by' => $user?->getAuthIdentifier(),
                'posted_at' => now(),
            ])->save();

            $component = $type === SavingsTransactionType::Deposit ? LineComponent::Deposit : LineComponent::Withdrawal;
            $memberLine = ['savings_account_id' => $account->id, 'member_id' => $account->member_id, 'component' => $component];
            $cashLine = ['cash_bank_account_id' => $cash->id, 'member_id' => $account->member_id, 'component' => $component];
            $lines = $type === SavingsTransactionType::Deposit
                ? [JournalService::debit($cash->account, $amount, $cashLine), JournalService::credit($scheme->liabilityAccount, $amount, $memberLine)]
                : [JournalService::debit($scheme->liabilityAccount, $amount, $memberLine), JournalService::credit($cash->account, $amount, $cashLine)];

            $entry = $this->journal->record([
                'branch_id' => $account->branch_id,
                'entry_date' => $data['transaction_date'],
                'description' => $type->label().' · '.$account->account_number.($transaction->description ? ' · '.$transaction->description : ''),
                'reference' => $transaction->reference,
                'source_type' => 'savings_transaction',
                'source_id' => $transaction->id,
                'number' => $number,
            ], $lines, $user);

            $transaction->forceFill(['journal_entry_id' => $entry->id])->save();
            $this->refreshBalance($account);

            return $transaction;
        });
    }

    private function assertCanWithdraw(SavingsAccount $account, Money $amount, string $date): void
    {
        $scheme = $account->scheme;
        if (! $scheme->allow_withdrawal) {
            throw new FinanceException(__(':scheme does not allow withdrawals.', ['scheme' => $scheme->name]));
        }

        if ($scheme->max_withdrawals_per_month) {
            $month = Carbon::parse($date);
            $count = SavingsTransaction::query()->withoutBranchScope()
                ->where('savings_account_id', $account->id)
                ->where('type', SavingsTransactionType::Withdrawal->value)
                ->where('status', EntryStatus::Posted->value)
                ->whereBetween('transaction_date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
                ->count();
            if ($count >= $scheme->max_withdrawals_per_month) {
                throw new FinanceException(__(':scheme allows :n withdrawals a month.', ['scheme' => $scheme->name, 'n' => $scheme->max_withdrawals_per_month]));
            }
        }

        $balance = $this->ledger->savingsBalance($account->id);
        $available = $balance->minus($scheme->minimum_balance);
        if ($amount->greaterThan($available)) {
            throw new InsufficientBalanceException(__('The balance is :balance and :minimum must stay; at most :available can be withdrawn.', [
                'balance' => $balance->format(),
                'minimum' => Money::of($scheme->minimum_balance)->format(),
                'available' => Money::max($available, 0)->format(),
            ]));
        }
    }

    private function lockAccount(int $id, ?Authenticatable $user): SavingsAccount
    {
        /** @var SavingsAccount $account */
        $account = SavingsAccount::query()->withoutBranchScope()->lockForUpdate()->findOrFail($id);
        $this->journal->assertBranch($user, $account->branch_id);
        $account->load('scheme.liabilityAccount');

        return $account;
    }
}
