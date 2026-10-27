<?php

namespace SCMS\Finance\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use SCMS\Finance\Enums\EntryStatus;
use SCMS\Finance\Models\LoanRepayment;
use SCMS\Finance\Support\Abilities;

class LoanRepaymentPolicy extends FinancePolicy
{
    public function view(Authenticatable $user, LoanRepayment $repayment): bool
    {
        return $this->allows($user, Abilities::LOAN_VIEW, $repayment);
    }

    public function reverse(Authenticatable $user, LoanRepayment $repayment): bool
    {
        return $repayment->status === EntryStatus::Posted && $this->allows($user, Abilities::REVERSE, $repayment);
    }
}
