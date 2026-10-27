<?php

namespace SCMS\Finance\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use SCMS\Finance\Enums\AccountType;
use SCMS\Finance\Exceptions\FinanceException;
use SCMS\Finance\Models\Account;
use SCMS\Finance\Models\CashBankAccount;
use SCMS\Finance\Models\JournalLine;
use SCMS\Finance\Models\LoanProduct;
use SCMS\Finance\Models\SavingsScheme;

/** Chart of accounts. Accounts with postings keep their type and stay postable; they are turned off, not deleted. */
class AccountService
{
    public function create(array $data, ?Authenticatable $user): Account
    {
        return DB::transaction(function () use ($data, $user) {
            $account = new Account(Arr::only($this->checked($data), (new Account())->getFillable()));
            $account->created_by = $user?->getAuthIdentifier();
            $account->save();

            return $account;
        });
    }

    public function update(Account $account, array $data): Account
    {
        return DB::transaction(function () use ($account, $data) {
            $account = Account::query()->lockForUpdate()->findOrFail($account->id);
            $data = $this->checked($data, $account);
            $used = $this->hasPostings($account);

            if ($account->is_system && isset($data['code']) && $data['code'] !== $account->code) {
                throw new FinanceException(__('The code of a system account cannot change; the package finds it by code.'));
            }
            if ($used && isset($data['type']) && $data['type'] !== $account->type->value) {
                throw new FinanceException(__('An account with postings keeps its type.'));
            }
            if ($used && array_key_exists('is_postable', $data) && ! $data['is_postable']) {
                throw new FinanceException(__('An account with postings cannot become a group account.'));
            }
            if (! empty($data['is_postable']) && $account->children()->exists()) {
                throw new FinanceException(__('An account with sub-accounts is a group account.'));
            }
            if ($used && array_key_exists('branch_id', $data) && (int) $data['branch_id'] !== (int) $account->branch_id) {
                throw new FinanceException(__('An account with postings keeps its branch.'));
            }

            $account->fill(Arr::only($data, $account->getFillable()))->save();

            return $account;
        });
    }

    public function delete(Account $account): void
    {
        DB::transaction(function () use ($account) {
            $account = Account::query()->lockForUpdate()->findOrFail($account->id);
            if ($account->is_system) {
                throw new FinanceException(__('System accounts cannot be deleted.'));
            }
            if ($this->hasPostings($account) || $account->children()->exists() || $this->isReferenced($account)) {
                throw new FinanceException(__('The account is in use; turn it off instead.'));
            }
            $account->delete();
        });
    }

    public function hasPostings(Account $account): bool
    {
        return JournalLine::query()->where('account_id', $account->id)->exists();
    }

    private function isReferenced(Account $account): bool
    {
        $id = $account->id;

        return CashBankAccount::query()->withoutBranchScope()->where('account_id', $id)->exists()
            || SavingsScheme::query()->where('liability_account_id', $id)->exists()
            || LoanProduct::query()->where(fn ($q) => $q->where('receivable_account_id', $id)->orWhere('interest_income_account_id', $id)
                ->orWhere('penalty_income_account_id', $id)->orWhere('fee_income_account_id', $id))->exists();
    }

    /** A sub-account has its parent's type and sits under a group account, never under itself. */
    private function checked(array $data, ?Account $account = null): array
    {
        if (! empty($data['parent_id'])) {
            $parent = Account::query()->findOrFail($data['parent_id']);
            if ($parent->is_postable) {
                throw new FinanceException(__(':account takes postings; choose a group account as parent.', ['account' => $parent->label()]));
            }
            $type = $data['type'] ?? $account?->type?->value;
            if ($type && $parent->type !== AccountType::from($type)) {
                throw new FinanceException(__('A sub-account has the type of its parent (:type).', ['type' => $parent->type->label()]));
            }
            if ($account && in_array((int) $parent->id, app(LedgerService::class)->descendantIds($account), true)) {
                throw new FinanceException(__('An account cannot sit under itself.'));
            }
        }

        return $data;
    }
}
