<?php

namespace SCMS\Finance\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use SCMS\Finance\Models\SavingsAccount;
use SCMS\Finance\Support\Abilities;

class SavingsAccountPolicy extends FinancePolicy
{
    public function view(Authenticatable $user, SavingsAccount $account): bool
    {
        return $this->allows($user, Abilities::SAVINGS_VIEW, $account);
    }

    public function deposit(Authenticatable $user, SavingsAccount $account): bool
    {
        return $account->isActive() && $this->allows($user, Abilities::SAVINGS_DEPOSIT, $account);
    }

    public function withdraw(Authenticatable $user, SavingsAccount $account): bool
    {
        return $account->isActive() && $this->allows($user, Abilities::SAVINGS_WITHDRAW, $account);
    }

    public function update(Authenticatable $user, SavingsAccount $account): bool
    {
        return $this->allows($user, Abilities::SAVINGS_MANAGE, $account);
    }
}
