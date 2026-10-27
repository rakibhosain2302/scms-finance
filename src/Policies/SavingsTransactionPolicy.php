<?php

namespace SCMS\Finance\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use SCMS\Finance\Enums\EntryStatus;
use SCMS\Finance\Models\SavingsTransaction;
use SCMS\Finance\Support\Abilities;

class SavingsTransactionPolicy extends FinancePolicy
{
    public function view(Authenticatable $user, SavingsTransaction $transaction): bool
    {
        return $this->allows($user, Abilities::SAVINGS_VIEW, $transaction);
    }

    public function reverse(Authenticatable $user, SavingsTransaction $transaction): bool
    {
        return $transaction->status === EntryStatus::Posted && $this->allows($user, Abilities::REVERSE, $transaction);
    }
}
