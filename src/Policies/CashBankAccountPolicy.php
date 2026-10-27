<?php

namespace SCMS\Finance\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use SCMS\Finance\Models\CashBankAccount;
use SCMS\Finance\Support\Abilities;

class CashBankAccountPolicy extends FinancePolicy
{
    public function view(Authenticatable $user, CashBankAccount $account): bool
    {
        return $this->allows($user, Abilities::CASHBANK_VIEW, $account);
    }

    public function update(Authenticatable $user, CashBankAccount $account): bool
    {
        return $this->allows($user, Abilities::CASHBANK_MANAGE, $account);
    }
}
