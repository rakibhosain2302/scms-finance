<?php

namespace SCMS\Finance\Services;

use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use SCMS\Finance\Contracts\BranchAccess;
use SCMS\Finance\Enums\AccountSubtype;
use SCMS\Finance\Enums\EntryStatus;
use SCMS\Finance\Enums\LineComponent;
use SCMS\Finance\Events\JournalPosted;
use SCMS\Finance\Events\TransactionReversed;
use SCMS\Finance\Exceptions\BranchAccessException;
use SCMS\Finance\Exceptions\FinanceException;
use SCMS\Finance\Exceptions\InsufficientBalanceException;
use SCMS\Finance\Exceptions\InvalidStateException;
use SCMS\Finance\Exceptions\UnbalancedJournalException;
use SCMS\Finance\Models\Account;
use SCMS\Finance\Models\CashBankAccount;
use SCMS\Finance\Models\JournalEntry;
use SCMS\Finance\Support\MakerChecker;
use SCMS\Finance\Support\Money;

/**
 * The only writer of journal entries.
 *
 * Every document service (deposit, repayment, voucher, …) calls record() inside its own
 * transaction, so the document and its entry are saved together or not at all. A posted entry
 * is never changed: reverse() posts a mirrored entry and marks the original "reversed".
 *
 * A line is ['account' => Account|'account_id' => int, 'debit' => '100.00' | 'credit' => '100.00',
 * 'description', 'component', 'cash_bank_account_id', 'member_id', 'savings_account_id', 'loan_id'].
 */
class JournalService
{
    public function __construct(
        private readonly TransactionNumberService $numbers,
        private readonly FinancialYearService $years,
        private readonly BranchAccess $branches,
        private readonly LedgerService $ledger,
    ) {
    }

    /** @param  array<string, mixed>  $extra */
    public static function debit(Account $account, Money|string $amount, array $extra = []): array
    {
        return ['account' => $account, 'debit' => Money::of($amount), 'credit' => Money::zero()] + $extra;
    }

    /** @param  array<string, mixed>  $extra */
    public static function credit(Account $account, Money|string $amount, array $extra = []): array
    {
        return ['account' => $account, 'debit' => Money::zero(), 'credit' => Money::of($amount)] + $extra;
    }

    /**
     * Writes and posts an entry for a document.
     *
     * @param  array{branch_id: int, entry_date: DateTimeInterface|string, description?: ?string, reference?: ?string, source_type: string, source_id?: ?int, number?: ?string, number_key?: string}  $header
     * @param  list<array<string, mixed>>  $lines
     */
    public function record(array $header, array $lines, ?Authenticatable $user): JournalEntry
    {
        return DB::transaction(function () use ($header, $lines, $user) {
            $branchId = (int) $header['branch_id'];
            $this->assertBranch($user, $branchId);
            $year = $this->years->openYearFor($header['entry_date']);

            $lines = $this->normalize($lines, $branchId, manual: false);
            $this->assertBalanced($lines);
            // Lock order everywhere: document rows, then number counters, then cash accounts.
            $number = $header['number'] ?? $this->numbers->next($header['number_key'] ?? 'journal', $header['entry_date'], $branchId);
            $this->assertCashAvailable($lines);

            $entry = new JournalEntry([
                'entry_date' => Carbon::parse($header['entry_date'])->toDateString(),
                'description' => $header['description'] ?? null,
                'reference' => $header['reference'] ?? null,
            ]);
            $entry->forceFill([
                'number' => $number,
                'branch_id' => $branchId,
                'financial_year_id' => $year->getKey(),
                'source_type' => $header['source_type'],
                'source_id' => $header['source_id'] ?? null,
                'status' => EntryStatus::Posted->value,
                'total' => $this->total($lines)->toString(),
                'created_by' => $user?->getAuthIdentifier(),
                'posted_by' => $user?->getAuthIdentifier(),
                'posted_at' => now(),
            ])->save();

            $this->writeLines($entry, $lines);

            return $entry;
        });
    }

    /**
     * A journal entry written by hand, kept as a draft until posted.
     *
     * @param  array{branch_id: int, entry_date: string, description?: ?string, reference?: ?string}  $header
     */
    public function createDraft(array $header, array $lines, ?Authenticatable $user): JournalEntry
    {
        return DB::transaction(function () use ($header, $lines, $user) {
            $branchId = (int) $header['branch_id'];
            $this->assertBranch($user, $branchId);
            $this->years->assertPostingDate($header['entry_date']);
            $year = $this->years->openYearFor($header['entry_date']);
            $lines = $this->normalize($lines, $branchId, manual: true);

            $entry = new JournalEntry([
                'entry_date' => Carbon::parse($header['entry_date'])->toDateString(),
                'description' => $header['description'] ?? null,
                'reference' => $header['reference'] ?? null,
            ]);
            $entry->forceFill([
                'number' => $this->numbers->next('journal', $header['entry_date'], $branchId),
                'branch_id' => $branchId,
                'financial_year_id' => $year->getKey(),
                'source_type' => 'manual',
                'status' => EntryStatus::Draft->value,
                'total' => $this->total($lines)->toString(),
                'created_by' => $user?->getAuthIdentifier(),
            ])->save();

            $this->writeLines($entry, $lines);

            return $entry;
        });
    }

    public function updateDraft(JournalEntry $entry, array $header, array $lines, ?Authenticatable $user): JournalEntry
    {
        return DB::transaction(function () use ($entry, $header, $lines, $user) {
            $entry = $this->lockManualDraft($entry);
            $this->assertBranch($user, $entry->branch_id);
            $this->years->assertPostingDate($header['entry_date']);
            $year = $this->years->openYearFor($header['entry_date']);
            $lines = $this->normalize($lines, (int) $entry->branch_id, manual: true);

            $entry->fill([
                'entry_date' => Carbon::parse($header['entry_date'])->toDateString(),
                'description' => $header['description'] ?? null,
                'reference' => $header['reference'] ?? null,
            ])->forceFill([
                'financial_year_id' => $year->getKey(),
                'total' => $this->total($lines)->toString(),
            ])->save();

            // Draft lines have never been in the ledger: replacing them loses no history.
            $entry->lines()->delete();
            $this->writeLines($entry, $lines);

            return $entry->refresh();
        });
    }

    public function cancelDraft(JournalEntry $entry, ?Authenticatable $user): JournalEntry
    {
        return DB::transaction(function () use ($entry, $user) {
            $entry = $this->lockManualDraft($entry);
            $this->assertBranch($user, $entry->branch_id);
            $entry->forceFill(['status' => EntryStatus::Cancelled->value])->save();

            return $entry;
        });
    }

    /** Posts a manual draft: balanced, accounts still usable, year open, cash available. */
    public function post(JournalEntry $entry, ?Authenticatable $user): JournalEntry
    {
        $entry = DB::transaction(function () use ($entry, $user) {
            $entry = $this->lockManualDraft($entry);
            $this->assertBranch($user, $entry->branch_id);
            MakerChecker::assertMayPost($user, $entry);
            $year = $this->years->openYearFor($entry->entry_date);

            $lines = $this->normalize($entry->lines()->get()->map(fn ($line) => $line->only([
                'account_id', 'debit', 'credit', 'description', 'component', 'cash_bank_account_id', 'member_id', 'savings_account_id', 'loan_id',
            ]))->all(), (int) $entry->branch_id, manual: true);
            $this->assertBalanced($lines);
            $this->assertCashAvailable($lines);

            $entry->forceFill([
                'financial_year_id' => $year->getKey(),
                'status' => EntryStatus::Posted->value,
                'total' => $this->total($lines)->toString(),
                'posted_by' => $user?->getAuthIdentifier(),
                'posted_at' => now(),
            ])->save();

            return $entry;
        });

        event(new JournalPosted($entry, $user?->getAuthIdentifier()));

        return $entry;
    }

    /**
     * Posts the mirror of a posted entry (debits become credits) and marks the original
     * "reversed". Both stay in the ledger, so history and every past report remain explainable.
     * Documents reverse through their own service, which also undoes their side effects.
     */
    public function reverse(JournalEntry $entry, string $reason, ?Authenticatable $user, DateTimeInterface|string|null $date = null): JournalEntry
    {
        if (trim($reason) === '') {
            throw new FinanceException(__('Give the reason for the reversal.'));
        }

        return DB::transaction(function () use ($entry, $reason, $user, $date) {
            /** @var JournalEntry $entry */
            $entry = JournalEntry::query()->withoutBranchScope()->lockForUpdate()->findOrFail($entry->getKey());
            $this->assertBranch($user, $entry->branch_id);

            if ($entry->status !== EntryStatus::Posted) {
                throw new InvalidStateException(__('Only a posted entry can be reversed; :number is :status.', ['number' => $entry->number, 'status' => $entry->status->label()]));
            }
            if ($entry->reversal_of_id) {
                throw new InvalidStateException(__('A reversal cannot be reversed. Post a new transaction instead.'));
            }

            $date = Carbon::parse($date ?? today())->startOfDay();
            if ($date->lt($entry->entry_date)) {
                throw new FinanceException(__('A reversal cannot be dated before the entry it reverses.'));
            }
            $year = $this->years->openYearFor($date);

            $lines = $entry->lines()->get()->map(fn ($line) => [
                'account_id' => $line->account_id,
                'debit' => Money::of($line->credit),
                'credit' => Money::of($line->debit),
                'description' => $line->description,
                'component' => $line->component?->value,
                'cash_bank_account_id' => $line->cash_bank_account_id,
                'member_id' => $line->member_id,
                'savings_account_id' => $line->savings_account_id,
                'loan_id' => $line->loan_id,
            ])->all();
            // Accounts turned off since may still take the reversal: the original went through them.
            $lines = $this->normalize($lines, (int) $entry->branch_id, manual: false, reversal: true);
            $this->assertBalanced($lines);
            $number = $this->numbers->next('reversal', $date, (int) $entry->branch_id);
            $this->assertCashAvailable($lines);

            $reversal = new JournalEntry([
                'entry_date' => $date->toDateString(),
                'description' => __('Reversal of :number: :reason', ['number' => $entry->number, 'reason' => $reason]),
                'reference' => $entry->number,
            ]);
            $reversal->forceFill([
                'number' => $number,
                'branch_id' => $entry->branch_id,
                'financial_year_id' => $year->getKey(),
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
                'status' => EntryStatus::Posted->value,
                'total' => $entry->total,
                'reversal_of_id' => $entry->getKey(),
                'reversal_reason' => mb_substr($reason, 0, 500),
                'created_by' => $user?->getAuthIdentifier(),
                'posted_by' => $user?->getAuthIdentifier(),
                'posted_at' => now(),
            ])->save();
            $this->writeLines($reversal, $lines);

            $entry->forceFill([
                'status' => EntryStatus::Reversed->value,
                'reversed_by' => $user?->getAuthIdentifier(),
                'reversed_at' => now(),
            ])->save();

            return $reversal;
        });
    }

    /** Reverses a manual journal entry (documents use their own service). */
    public function reverseManual(JournalEntry $entry, string $reason, ?Authenticatable $user, DateTimeInterface|string|null $date = null): JournalEntry
    {
        if (! $entry->isManual()) {
            throw new InvalidStateException(__('This entry belongs to :source :id; reverse it there.', ['source' => str_replace('_', ' ', $entry->source_type), 'id' => $entry->source_id]));
        }

        $reversal = $this->reverse($entry, $reason, $user, $date);
        event(new TransactionReversed($entry->refresh(), $reversal, $reason, $user?->getAuthIdentifier()));

        return $reversal;
    }

    /**
     * Total debit = total credit, and more than zero.
     *
     * @param  list<array{debit: Money, credit: Money}>  $lines
     */
    public function assertBalanced(array $lines): void
    {
        if (count($lines) < 2) {
            throw new UnbalancedJournalException(__('A journal entry needs at least two lines.'));
        }

        $debit = Money::sum(array_column($lines, 'debit'));
        $credit = Money::sum(array_column($lines, 'credit'));

        if (! $debit->equals($credit)) {
            throw new UnbalancedJournalException(__('Debits (:debit) and credits (:credit) must be equal.', ['debit' => $debit->toString(), 'credit' => $credit->toString()]));
        }
        if (! $debit->isPositive()) {
            throw new UnbalancedJournalException(__('A journal entry cannot be zero.'));
        }
    }

    /**
     * Checks and completes lines: amounts, accounts usable in the branch, sub-ledgers of the branch.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    public function normalize(array $lines, int $branchId, bool $manual, bool $reversal = false): array
    {
        $lines = array_values(array_filter($lines, fn ($line) => is_array($line)));
        $accountIds = collect($lines)->map(fn ($l) => isset($l['account']) ? $l['account']->getKey() : ($l['account_id'] ?? null))->filter()->unique();
        $accounts = Account::query()->whereKey($accountIds->all())->get()->keyBy('id');
        $cashIds = collect($lines)->pluck('cash_bank_account_id')->filter()->unique();
        $cashAccounts = CashBankAccount::query()->withoutBranchScope()->whereKey($cashIds->all())->get()->keyBy('id');

        $result = [];
        foreach ($lines as $i => $line) {
            $n = $i + 1;
            $account = $line['account'] ?? $accounts->get((int) ($line['account_id'] ?? 0));
            if (! $account instanceof Account) {
                throw new FinanceException(__('Line :n: choose an account.', ['n' => $n]));
            }
            if (! $reversal && (! $account->is_postable || ! $account->is_active)) {
                throw new FinanceException(__('Line :n: :account cannot take postings (a group or turned off account).', ['n' => $n, 'account' => $account->label()]));
            }
            if (! $account->isUsableIn($branchId)) {
                throw new FinanceException(__('Line :n: :account belongs to another branch.', ['n' => $n, 'account' => $account->label()]));
            }

            $debit = Money::of($line['debit'] ?? null);
            $credit = Money::of($line['credit'] ?? null);
            if ($debit->isNegative() || $credit->isNegative()) {
                throw new FinanceException(__('Line :n: amounts cannot be negative.', ['n' => $n]));
            }
            if ($debit->isPositive() === $credit->isPositive()) {
                throw new FinanceException(__('Line :n: enter either a debit or a credit.', ['n' => $n]));
            }

            $cashId = $line['cash_bank_account_id'] ?? null;
            if ($cashId) {
                $cash = $cashAccounts->get((int) $cashId);
                if (! $cash || (int) $cash->branch_id !== $branchId) {
                    throw new BranchAccessException(__('Line :n: the cash or bank account belongs to another branch.', ['n' => $n]));
                }
                if ((int) $cash->account_id !== (int) $account->getKey()) {
                    throw new FinanceException(__('Line :n: :name posts to account :code.', ['n' => $n, 'name' => $cash->name, 'code' => $cash->account?->code]));
                }
                if (! $reversal && ! $cash->is_active) {
                    throw new FinanceException(__(':name is turned off.', ['name' => $cash->name]));
                }
            }

            if ($manual) {
                // Cash and bank need the drawer or bank account, so its balance stays right.
                if (in_array($account->subtype, [AccountSubtype::Cash, AccountSubtype::Bank], true) && ! $cashId) {
                    throw new FinanceException(__('Line :n: choose the cash or bank account for :account.', ['n' => $n, 'account' => $account->label()]));
                }
                // Member balances move only through the savings and loan screens.
                if (in_array($account->subtype, [AccountSubtype::MemberSavings, AccountSubtype::LoanReceivable], true)) {
                    throw new FinanceException(__('Line :n: :account is a member ledger; use the savings or loan screens.', ['n' => $n, 'account' => $account->label()]));
                }
            }

            $component = $line['component'] ?? null;
            $result[] = [
                'account_id' => (int) $account->getKey(),
                'debit' => $debit,
                'credit' => $credit,
                'description' => isset($line['description']) ? mb_substr((string) $line['description'], 0, 500) : null,
                'component' => $component instanceof LineComponent ? $component->value : $component,
                'cash_bank_account_id' => $cashId ? (int) $cashId : null,
                'member_id' => $line['member_id'] ?? null,
                'savings_account_id' => $line['savings_account_id'] ?? null,
                'loan_id' => $line['loan_id'] ?? null,
            ];
        }

        return $result;
    }

    /**
     * Refuses an entry that would take a cash drawer or bank account below zero
     * (scms-finance.rules.prevent_negative_cash). The account rows are locked in id order, so
     * two payments from one drawer run one after the other and never both pass the check.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    public function assertCashAvailable(array $lines): void
    {
        /** @var Collection<int, Money> $outflow */
        $outflow = collect($lines)->filter(fn ($l) => $l['cash_bank_account_id'])
            ->groupBy('cash_bank_account_id')
            ->map(fn ($group) => Money::sum($group->pluck('credit'))->minus(Money::sum($group->pluck('debit'))))
            ->filter(fn (Money $net) => $net->isPositive())
            ->sortKeys();

        if ($outflow->isEmpty()) {
            return;
        }

        $locked = CashBankAccount::query()->withoutBranchScope()->whereKey($outflow->keys()->all())->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        if (! config('scms-finance.rules.prevent_negative_cash', true)) {
            return;
        }

        foreach ($outflow as $id => $amount) {
            $balance = $this->ledger->cashBankBalance((int) $id);
            if ($balance->lessThan($amount)) {
                throw new InsufficientBalanceException(__(':name has :balance; :amount is needed.', [
                    'name' => $locked[$id]->name ?? '#'.$id, 'balance' => $balance->format(), 'amount' => $amount->format(),
                ]));
            }
        }
    }

    public function assertBranch(?Authenticatable $user, int|string|null $branchId): void
    {
        // Console commands and approval handlers run without a user: the caller decided.
        if ($user !== null && ! $this->branches->canAccess($user, $branchId)) {
            throw new BranchAccessException();
        }
    }

    /** @param  list<array{debit: Money}>  $lines */
    private function total(array $lines): Money
    {
        return Money::sum(array_column($lines, 'debit'));
    }

    /** @param  list<array<string, mixed>>  $lines */
    private function writeLines(JournalEntry $entry, array $lines): void
    {
        $no = 0;
        foreach ($lines as $line) {
            $entry->lines()->create([
                'line_no' => ++$no,
                'debit' => $line['debit']->toString(),
                'credit' => $line['credit']->toString(),
            ] + $line);
        }
    }

    private function lockManualDraft(JournalEntry $entry): JournalEntry
    {
        /** @var JournalEntry $locked */
        $locked = JournalEntry::query()->withoutBranchScope()->lockForUpdate()->findOrFail($entry->getKey());

        if (! $locked->isManual()) {
            throw new InvalidStateException(__('Entries of documents change only through their document.'));
        }
        if ($locked->status !== EntryStatus::Draft) {
            throw new InvalidStateException(__(':number is :status; only drafts can change.', ['number' => $locked->number, 'status' => $locked->status->label()]));
        }

        return $locked;
    }
}
