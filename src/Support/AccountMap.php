<?php

namespace SCMS\Finance\Support;

use SCMS\Finance\Exceptions\FinanceException;
use SCMS\Finance\Models\Account;

/**
 * The accounts the package posts to by role ("cash", "member_savings", …), found by the codes in
 * config('scms-finance.accounts'). Never by id: ids differ between installs.
 */
class AccountMap
{
    /** @var array<string, Account> */
    private array $resolved = [];

    public function get(string $role): Account
    {
        if (isset($this->resolved[$role])) {
            return $this->resolved[$role];
        }

        $code = config("scms-finance.accounts.{$role}");
        $account = $code ? Account::query()->where('code', $code)->first() : null;

        if (! $account) {
            throw new FinanceException(__('The ":role" account (code :code) is missing from the chart of accounts. Run the finance seeder or change scms-finance.accounts.', ['role' => $role, 'code' => $code ?? '—']));
        }

        return $this->resolved[$role] = $account;
    }

    public function find(string $role): ?Account
    {
        try {
            return $this->get($role);
        } catch (FinanceException) {
            return null;
        }
    }

    public function flush(): void
    {
        $this->resolved = [];
    }
}
