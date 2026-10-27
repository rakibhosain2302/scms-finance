<?php

namespace SCMS\Finance\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use SCMS\Finance\Enums\AccountType;
use SCMS\Finance\Enums\EntryStatus;
use SCMS\Finance\Enums\LineComponent;
use SCMS\Finance\Events\FinanceEvent;
use SCMS\Finance\Events\TransactionReversed;
use SCMS\Finance\Exceptions\BranchAccessException;
use SCMS\Finance\Exceptions\FinanceException;
use SCMS\Finance\Exceptions\InvalidStateException;
use SCMS\Finance\Models\Account;
use SCMS\Finance\Models\CashBankAccount;
use SCMS\Finance\Models\Voucher;
use SCMS\Finance\Support\MakerChecker;
use SCMS\Finance\Support\Money;
use Throwable;

/**
 * Income and expense vouchers: written as a draft (or posted at once), posted, reversed.
 *
 *     income   Dr Cash/Bank       Cr Income account
 *     expense  Dr Expense account Cr Cash/Bank
 */
abstract class VoucherService
{
    public function __construct(
        protected readonly JournalService $journal,
        protected readonly FinancialYearService $years,
        protected readonly TransactionNumberService $numbers,
    ) {
    }

    /** @return class-string<Voucher> */
    abstract protected function model(): string;

    abstract protected function accountType(): AccountType;

    abstract protected function postedEvent(Voucher $voucher, ?Authenticatable $user): FinanceEvent;

    /** @return list<array<string, mixed>> */
    abstract protected function lines(Voucher $voucher, Account $account, CashBankAccount $cash): array;

    /** @param  array{branch_id: int, voucher_date: string, account_id: int, cash_bank_account_id: int, amount: string}  $data */
    public function create(array $data, ?UploadedFile $attachment, bool $post, ?Authenticatable $user): Voucher
    {
        $branchId = (int) $data['branch_id'];
        $this->journal->assertBranch($user, $branchId);
        $path = $this->store($attachment);

        try {
            $voucher = DB::transaction(function () use ($data, $branchId, $path, $user) {
                $model = $this->model();
                $voucher = new $model();
                $voucher->fill(Arr::only($data, $voucher->getFillable()));
                $this->check($voucher, $branchId);
                $voucher->forceFill([
                    'number' => $this->numbers->next($model::kind(), $data['voucher_date'], $branchId),
                    'branch_id' => $branchId,
                    'financial_year_id' => $this->years->openYearFor($data['voucher_date'])->getKey(),
                    'attachment_path' => $path,
                    'status' => EntryStatus::Draft->value,
                    'created_by' => $user?->getAuthIdentifier(),
                ])->save();

                return $voucher;
            });
        } catch (Throwable $e) {
            $this->forget($path);
            throw $e;
        }

        return $post ? $this->post($voucher, $user) : $voucher;
    }

    public function update(Voucher $voucher, array $data, ?UploadedFile $attachment, ?Authenticatable $user): Voucher
    {
        $path = $this->store($attachment);

        try {
            return DB::transaction(function () use ($voucher, $data, $path, $user) {
                $voucher = $this->lockDraft($voucher, $user);
                $old = $voucher->attachment_path;
                $voucher->fill(Arr::only($data, $voucher->getFillable()));
                $this->check($voucher, (int) $voucher->branch_id);
                $voucher->forceFill([
                    'financial_year_id' => $this->years->openYearFor($voucher->voucher_date)->getKey(),
                    'attachment_path' => $path ?? $old,
                ])->save();
                if ($path && $old) {
                    DB::afterCommit(fn () => $this->forget($old));
                }

                return $voucher;
            });
        } catch (Throwable $e) {
            $this->forget($path);
            throw $e;
        }
    }

    public function post(Voucher $voucher, ?Authenticatable $user): Voucher
    {
        $voucher = DB::transaction(function () use ($voucher, $user) {
            $voucher = $this->lockDraft($voucher, $user);
            MakerChecker::assertMayPost($user, $voucher);
            [$account, $cash] = $this->check($voucher, (int) $voucher->branch_id);

            $entry = $this->journal->record([
                'branch_id' => $voucher->branch_id,
                'entry_date' => $voucher->voucher_date,
                'description' => $voucher->description ?: $account->name,
                'reference' => $voucher->reference,
                'source_type' => $this->model()::kind(),
                'source_id' => $voucher->id,
                'number' => $voucher->number,
            ], $this->lines($voucher, $account, $cash), $user);

            $voucher->forceFill([
                'journal_entry_id' => $entry->id,
                'financial_year_id' => $entry->financial_year_id,
                'status' => EntryStatus::Posted->value,
                'posted_by' => $user?->getAuthIdentifier(),
                'posted_at' => now(),
            ])->save();

            return $voucher;
        });

        event($this->postedEvent($voucher, $user));

        return $voucher;
    }

    public function cancel(Voucher $voucher, ?Authenticatable $user): Voucher
    {
        return DB::transaction(function () use ($voucher, $user) {
            $voucher = $this->lockDraft($voucher, $user);
            $voucher->forceFill(['status' => EntryStatus::Cancelled->value])->save();

            return $voucher;
        });
    }

    public function reverse(Voucher $voucher, string $reason, ?Authenticatable $user): Voucher
    {
        [$voucher, $reversal] = DB::transaction(function () use ($voucher, $reason, $user) {
            $voucher = $this->model()::query()->withoutBranchScope()->lockForUpdate()->findOrFail($voucher->id);
            $this->journal->assertBranch($user, $voucher->branch_id);
            if ($voucher->status !== EntryStatus::Posted) {
                throw new InvalidStateException(__(':number is :status.', ['number' => $voucher->number, 'status' => $voucher->status->label()]));
            }

            $reversal = $this->journal->reverse($voucher->journalEntry, $reason, $user);
            $voucher->forceFill(['status' => EntryStatus::Reversed->value, 'reversal_entry_id' => $reversal->id])->save();

            return [$voucher, $reversal];
        });

        event(new TransactionReversed($voucher, $reversal, $reason, $user?->getAuthIdentifier()));

        return $voucher;
    }

    /** @return array{0: Account, 1: CashBankAccount} */
    protected function check(Voucher $voucher, int $branchId): array
    {
        if (! Money::of($voucher->amount)->isPositive()) {
            throw new FinanceException(__('The amount must be more than zero.'));
        }
        $this->years->assertPostingDate($voucher->voucher_date);

        $account = Account::query()->findOrFail($voucher->account_id);
        if ($account->type !== $this->accountType() || ! $account->is_postable || ! $account->is_active || ! $account->isUsableIn($branchId)) {
            throw new FinanceException(__(':account is not an active :type account of this branch.', ['account' => $account->label(), 'type' => $this->accountType()->label()]));
        }

        $cash = CashBankAccount::query()->withoutBranchScope()->with('account')->findOrFail($voucher->cash_bank_account_id);
        if ((int) $cash->branch_id !== $branchId) {
            throw new BranchAccessException(__('The cash or bank account belongs to another branch.'));
        }
        if (! $cash->is_active) {
            throw new FinanceException(__(':name is turned off.', ['name' => $cash->name]));
        }

        return [$account, $cash];
    }

    protected function lockDraft(Voucher $voucher, ?Authenticatable $user): Voucher
    {
        $voucher = $this->model()::query()->withoutBranchScope()->lockForUpdate()->findOrFail($voucher->id);
        $this->journal->assertBranch($user, $voucher->branch_id);
        if ($voucher->status !== EntryStatus::Draft) {
            throw new InvalidStateException(__(':number is :status; only drafts can change.', ['number' => $voucher->number, 'status' => $voucher->status->label()]));
        }

        return $voucher;
    }

    protected function cashLine(Voucher $voucher, CashBankAccount $cash): array
    {
        return ['cash_bank_account_id' => $cash->id, 'component' => LineComponent::Payment];
    }

    private function store(?UploadedFile $file): ?string
    {
        return $file ? $file->store(trim((string) config('scms-finance.attachments.directory', 'finance'), '/').'/'.$this->model()::kind(), config('scms-finance.attachments.disk', 'public')) : null;
    }

    private function forget(?string $path): void
    {
        if ($path) {
            Storage::disk(config('scms-finance.attachments.disk', 'public'))->delete($path);
        }
    }
}
