<?php

namespace SCMS\Finance\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use SCMS\Finance\Enums\LoanStatus;
use SCMS\Finance\Models\Loan;
use SCMS\Finance\Support\Abilities;

class LoanPolicy extends FinancePolicy
{
    public function view(Authenticatable $user, Loan $loan): bool
    {
        return $this->allows($user, Abilities::LOAN_VIEW, $loan);
    }

    public function approve(Authenticatable $user, Loan $loan): bool
    {
        return $loan->status === LoanStatus::Applied && $this->allows($user, Abilities::LOAN_APPROVE, $loan);
    }

    public function cancel(Authenticatable $user, Loan $loan): bool
    {
        return in_array($loan->status, [LoanStatus::Applied, LoanStatus::Approved], true)
            && ($this->allows($user, Abilities::LOAN_APPROVE, $loan) || $this->allows($user, Abilities::LOAN_CREATE, $loan));
    }

    public function disburse(Authenticatable $user, Loan $loan): bool
    {
        return $loan->status === LoanStatus::Approved && $this->allows($user, Abilities::LOAN_DISBURSE, $loan);
    }

    public function repay(Authenticatable $user, Loan $loan): bool
    {
        return $loan->status->isRunning() && $this->allows($user, Abilities::LOAN_REPAY, $loan);
    }

    public function reverseDisbursement(Authenticatable $user, Loan $loan): bool
    {
        return $loan->status === LoanStatus::Active && $this->allows($user, Abilities::REVERSE, $loan);
    }
}
