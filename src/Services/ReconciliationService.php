<?php

namespace SCMS\Finance\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use SCMS\Finance\Enums\EntryStatus;
use SCMS\Finance\Exceptions\FinanceException;
use SCMS\Finance\Exceptions\InvalidStateException;
use SCMS\Finance\Models\CashBankAccount;
use SCMS\Finance\Models\JournalLine;
use SCMS\Finance\Models\Reconciliation;
use SCMS\Finance\Support\Money;

/**
 * Matches a bank statement with the ledger. The lines ticked off (cleared) plus every line cleared
 * by earlier reconciliations must add up to the statement's closing balance before the
 * reconciliation can be completed; what is left uncleared (cheques not yet presented, deposits in
 * transit) explains the difference between the book and the bank. Nothing is posted here.
 */
class ReconciliationService
{
    public function __construct(private readonly JournalService $journal, private readonly LedgerService $ledger)
    {
    }

    /** @param  array{statement_date: string, statement_balance: string, note?: ?string}  $data */
    public function start(CashBankAccount $account, array $data, ?Authenticatable $user): Reconciliation
    {
        $this->journal->assertBranch($user, $account->branch_id);

        return DB::transaction(function () use ($account, $data, $user) {
            $account = CashBankAccount::query()->withoutBranchScope()->lockForUpdate()->findOrFail($account->id);
            if (Reconciliation::query()->withoutBranchScope()->where('cash_bank_account_id', $account->id)->where('status', Reconciliation::DRAFT)->exists()) {
                throw new InvalidStateException(__(':name has an unfinished reconciliation; finish or delete it first.', ['name' => $account->name]));
            }
            $last = $this->lastCompleted($account);
            if ($last && Carbon::parse($data['statement_date'])->lte($last->statement_date)) {
                throw new FinanceException(__('The statement must be dated after the last reconciliation (:date).', ['date' => $last->statement_date->format('j M Y')]));
            }

            $reconciliation = new Reconciliation(['note' => $data['note'] ?? null]);
            $reconciliation->forceFill([
                'branch_id' => $account->branch_id,
                'cash_bank_account_id' => $account->id,
                'statement_date' => Carbon::parse($data['statement_date'])->toDateString(),
                'statement_balance' => Money::of($data['statement_balance'])->toString(),
                'status' => Reconciliation::DRAFT,
                'created_by' => $user?->getAuthIdentifier(),
            ])->save();

            return $reconciliation;
        });
    }

    /**
     * Lines of the account up to the statement date not cleared by another reconciliation.
     *
     * @return Collection<int, JournalLine>
     */
    public function candidates(Reconciliation $reconciliation): Collection
    {
        return JournalLine::query()
            ->select('finance_journal_lines.*')
            ->join('finance_journal_entries as e', 'e.id', '=', 'finance_journal_lines.journal_entry_id')
            ->where('finance_journal_lines.cash_bank_account_id', $reconciliation->cash_bank_account_id)
            ->whereIn('e.status', [EntryStatus::Posted->value, EntryStatus::Reversed->value])
            ->whereDate('e.entry_date', '<=', $reconciliation->statement_date->toDateString())
            ->whereNotIn('finance_journal_lines.id', fn ($q) => $q->select('journal_line_id')->from('finance_reconciliation_lines')->where('reconciliation_id', '!=', $reconciliation->id))
            ->with('entry:id,number,entry_date,description,status')
            ->orderBy('e.entry_date')->orderBy('finance_journal_lines.id')
            ->get();
    }

    /** @param  list<int>  $lineIds  the lines ticked off, replacing the earlier choice */
    public function clear(Reconciliation $reconciliation, array $lineIds, ?Authenticatable $user): Reconciliation
    {
        return DB::transaction(function () use ($reconciliation, $lineIds, $user) {
            $reconciliation = $this->lockDraft($reconciliation, $user);
            $allowed = $this->candidates($reconciliation)->pluck('id')->map(fn ($id) => (int) $id)->all();
            $ids = array_values(array_unique(array_map('intval', $lineIds)));
            if (array_diff($ids, $allowed) !== []) {
                throw new FinanceException(__('Some lines are not of this account, are dated after the statement or were cleared before.'));
            }
            $reconciliation->lines()->sync($ids);

            return $reconciliation;
        });
    }

    public function complete(Reconciliation $reconciliation, ?Authenticatable $user): Reconciliation
    {
        return DB::transaction(function () use ($reconciliation, $user) {
            $reconciliation = $this->lockDraft($reconciliation, $user);
            $summary = $this->summary($reconciliation);
            if (! $summary['difference']->isZero()) {
                throw new FinanceException(__('The cleared balance (:cleared) differs from the statement (:statement) by :difference.', [
                    'cleared' => $summary['cleared']->format(), 'statement' => $summary['statement']->format(), 'difference' => $summary['difference']->format(),
                ]));
            }

            $reconciliation->forceFill([
                'status' => Reconciliation::COMPLETED,
                'cleared_balance' => $summary['cleared']->toString(),
                'book_balance' => $summary['book']->toString(),
                'completed_by' => $user?->getAuthIdentifier(),
                'completed_at' => now(),
            ])->save();

            return $reconciliation;
        });
    }

    public function delete(Reconciliation $reconciliation, ?Authenticatable $user): void
    {
        DB::transaction(fn () => $this->lockDraft($reconciliation, $user)->delete());
    }

    /**
     * statement: the bank's balance · cleared: lines cleared before plus these ·
     * book: the ledger balance at the statement date · difference: statement − cleared ·
     * uncleared: book − cleared (items in transit).
     *
     * @return array{statement: Money, cleared: Money, book: Money, difference: Money, uncleared: Money}
     */
    public function summary(Reconciliation $reconciliation): array
    {
        $cleared = $this->net(DB::table('finance_reconciliation_lines as r')
            ->join('finance_journal_lines as l', 'l.id', '=', 'r.journal_line_id')
            ->join('finance_reconciliations as rc', 'rc.id', '=', 'r.reconciliation_id')
            ->where('rc.cash_bank_account_id', $reconciliation->cash_bank_account_id)
            ->where(fn ($q) => $q->where('rc.status', Reconciliation::COMPLETED)->orWhere('rc.id', $reconciliation->id)));
        $book = $this->ledger->cashBankBalance((int) $reconciliation->cash_bank_account_id, $reconciliation->statement_date);
        $statement = Money::of($reconciliation->statement_balance);

        return [
            'statement' => $statement,
            'cleared' => $cleared,
            'book' => $book,
            'difference' => $statement->minus($cleared),
            'uncleared' => $book->minus($cleared),
        ];
    }

    private function lastCompleted(CashBankAccount $account): ?Reconciliation
    {
        return Reconciliation::query()->withoutBranchScope()->where('cash_bank_account_id', $account->id)
            ->where('status', Reconciliation::COMPLETED)->latest('statement_date')->first();
    }

    private function lockDraft(Reconciliation $reconciliation, ?Authenticatable $user): Reconciliation
    {
        $reconciliation = Reconciliation::query()->withoutBranchScope()->lockForUpdate()->findOrFail($reconciliation->id);
        $this->journal->assertBranch($user, $reconciliation->branch_id);
        if (! $reconciliation->isDraft()) {
            throw new InvalidStateException(__('A completed reconciliation cannot change.'));
        }

        return $reconciliation;
    }

    private function net($query): Money
    {
        $row = $query->selectRaw('COALESCE(SUM(l.debit), 0) as debit, COALESCE(SUM(l.credit), 0) as credit')->first();

        return Money::of((string) $row->debit)->minus((string) $row->credit);
    }
}
