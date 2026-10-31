<?php

namespace SCMS\Finance\Services;

use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use SCMS\Finance\Contracts\MemberDirectory;
use SCMS\Finance\Enums\SavingsAccountStatus;
use SCMS\Finance\Exceptions\BranchAccessException;
use SCMS\Finance\Exceptions\FinanceException;
use SCMS\Finance\Exceptions\InvalidStateException;
use SCMS\Finance\Models\SavingsAccount;
use SCMS\Finance\Models\SavingsScheme;
use SCMS\Finance\Support\Abilities;
use SCMS\Finance\Support\Money;

/** Savings accounts: opening, dormancy, closing. Money moves through SavingsTransactionService. */
class SavingsService
{
    public function __construct(
        private readonly JournalService $journal,
        private readonly LedgerService $ledger,
        private readonly TransactionNumberService $numbers,
        private readonly MemberDirectory $members,
        private readonly SavingsTransactionService $transactions,
    ) {
    }

    /**
     * Opens an account; opening_balance (go-live) brings in what the member already had, dated opened_on.
     *
     * @param  array{branch_id: int, member_id: int, savings_scheme_id: int, opened_on: string, note?: ?string, opening_balance?: ?string}  $data
     */
    public function open(array $data, ?Authenticatable $user): SavingsAccount
    {
        $branchId = (int) $data['branch_id'];
        $this->journal->assertBranch($user, $branchId);
        $this->assertMemberOfBranch($data['member_id'], $branchId);
        $opening = Money::of($data['opening_balance'] ?? null);
        if ($opening->isPositive() && $user && ! $user->can(Abilities::SAVINGS_OPENING)) {
            throw new FinanceException(__('Entering an opening balance needs its own permission.'));
        }

        return DB::transaction(function () use ($data, $branchId, $user, $opening) {
            $scheme = SavingsScheme::query()->findOrFail($data['savings_scheme_id']);
            if (! $scheme->is_active) {
                throw new FinanceException(__('The scheme :name is turned off.', ['name' => $scheme->name]));
            }

            $account = new SavingsAccount(['note' => $data['note'] ?? null]);
            $account->forceFill([
                'account_number' => $this->numbers->next('savings_account', $data['opened_on'], $branchId),
                'branch_id' => $branchId,
                'member_id' => $data['member_id'],
                'savings_scheme_id' => $scheme->id,
                'opened_on' => Carbon::parse($data['opened_on'])->toDateString(),
                'status' => SavingsAccountStatus::Active->value,
                'balance' => '0',
                'maturity_date' => $scheme->term_months ? Carbon::parse($data['opened_on'])->addMonthsNoOverflow((int) $scheme->term_months)->toDateString() : null,
                'installment_amount' => Money::of(filled($data['installment_amount'] ?? null) ? $data['installment_amount'] : $scheme->min_deposit)->toString(),
                'created_by' => $user?->getAuthIdentifier(),
            ])->save();

            if ($opening->isPositive()) {
                $this->transactions->creditOpeningBalance($account, $opening, $account->opened_on->toDateString(), $user);
            }

            return $account->refresh();
        });
    }

    public function setStatus(SavingsAccount $account, SavingsAccountStatus $status, ?Authenticatable $user, DateTimeInterface|string|null $date = null): SavingsAccount
    {
        return DB::transaction(function () use ($account, $status, $user, $date) {
            /** @var SavingsAccount $account */
            $account = SavingsAccount::query()->withoutBranchScope()->lockForUpdate()->findOrFail($account->id);
            $this->journal->assertBranch($user, $account->branch_id);

            if ($account->status === SavingsAccountStatus::Closed) {
                throw new InvalidStateException(__('A closed account stays closed.'));
            }
            if ($status === SavingsAccountStatus::Closed && ! $this->ledger->savingsBalance($account->id)->isZero()) {
                throw new InvalidStateException(__('Withdraw the balance before closing the account.'));
            }

            $account->forceFill([
                'status' => $status->value,
                'closed_on' => $status === SavingsAccountStatus::Closed ? Carbon::parse($date ?? today())->toDateString() : null,
            ])->save();

            return $account;
        });
    }

    public function assertMemberOfBranch(int|string $memberId, int $branchId): void
    {
        if (! $this->members->available()) {
            throw new FinanceException(__('No member model is configured (scms-finance.models.member).'));
        }
        $member = $this->members->find($memberId) ?? throw new FinanceException(__('The member does not exist.'));
        $memberBranch = $this->members->branchId($member);
        if ($memberBranch !== null && $memberBranch !== $branchId) {
            throw new BranchAccessException(__('The member belongs to another branch.'));
        }
    }
}
