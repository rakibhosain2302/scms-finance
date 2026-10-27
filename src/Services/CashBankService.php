<?php

namespace SCMS\Finance\Services;

use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use SCMS\Finance\Enums\CashBankKind;
use SCMS\Finance\Enums\LineComponent;
use SCMS\Finance\Exceptions\FinanceException;
use SCMS\Finance\Models\Account;
use SCMS\Finance\Models\CashBankAccount;
use SCMS\Finance\Support\AccountMap;
use SCMS\Finance\Support\Money;

/**
 * Cash drawers and bank accounts. The opening balance is a posted entry
 * (Dr cash/bank, Cr opening balance equity), so the ledger stays the only balance.
 */
class CashBankService
{
    public function __construct(
        private readonly JournalService $journal,
        private readonly LedgerService $ledger,
        private readonly AccountMap $accounts,
    ) {
    }

    public function create(int $branchId, CashBankKind $kind, int $accountId, array $data, ?Authenticatable $user, Money|string|null $opening = null, DateTimeInterface|string|null $openingDate = null): CashBankAccount
    {
        $this->journal->assertBranch($user, $branchId);
        $opening = Money::of($opening);
        if ($opening->isNegative()) {
            throw new FinanceException(__('The opening balance cannot be negative.'));
        }

        return DB::transaction(function () use ($branchId, $kind, $accountId, $data, $user, $opening, $openingDate) {
            $account = Account::query()->findOrFail($accountId);
            if (! $account->is_postable || $account->subtype !== $kind->subtype() || ! $account->isUsableIn($branchId)) {
                throw new FinanceException(__(':account is not a :kind account of this branch.', ['account' => $account->label(), 'kind' => $kind->label()]));
            }

            $cash = new CashBankAccount(Arr::only($data, (new CashBankAccount())->getFillable()));
            $cash->forceFill([
                'branch_id' => $branchId,
                'kind' => $kind->value,
                'account_id' => $account->id,
                'created_by' => $user?->getAuthIdentifier(),
            ])->save();

            if ($opening->isPositive()) {
                $this->journal->record([
                    'branch_id' => $branchId,
                    'entry_date' => $openingDate ?? today(),
                    'description' => __('Opening balance of :name', ['name' => $cash->name]),
                    'source_type' => 'opening_balance',
                    'source_id' => $cash->id,
                    'number_key' => 'opening_balance',
                ], [
                    JournalService::debit($account, $opening, ['cash_bank_account_id' => $cash->id, 'component' => LineComponent::Opening]),
                    JournalService::credit($this->accounts->get('opening_balance_equity'), $opening, ['component' => LineComponent::Opening]),
                ], $user);
            }

            return $cash;
        });
    }

    public function update(CashBankAccount $cash, array $data, ?Authenticatable $user): CashBankAccount
    {
        $this->journal->assertBranch($user, $cash->branch_id);
        $cash->fill(Arr::only($data, $cash->getFillable()))->save();

        return $cash;
    }

    public function balance(CashBankAccount $cash, DateTimeInterface|string|null $asOf = null): Money
    {
        return $this->ledger->cashBankBalance((int) $cash->id, $asOf);
    }
}
