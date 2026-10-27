<?php

namespace SCMS\Finance\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use SCMS\Finance\Enums\EntryStatus;
use SCMS\Finance\Models\Transfer;
use SCMS\Finance\Support\Abilities;
use SCMS\Finance\Support\MakerChecker;

class TransferPolicy extends FinancePolicy
{
    public function view(Authenticatable $user, Transfer $transfer): bool
    {
        return $this->allows($user, Abilities::TRANSFER_VIEW, $transfer);
    }

    public function update(Authenticatable $user, Transfer $transfer): bool
    {
        return $transfer->status === EntryStatus::Draft && $this->allows($user, Abilities::TRANSFER_CREATE, $transfer);
    }

    public function post(Authenticatable $user, Transfer $transfer): bool
    {
        return $transfer->status === EntryStatus::Draft && $this->allows($user, Abilities::TRANSFER_POST, $transfer) && MakerChecker::mayPost($user, $transfer);
    }

    public function reverse(Authenticatable $user, Transfer $transfer): bool
    {
        return $transfer->status === EntryStatus::Posted && $this->allows($user, Abilities::REVERSE, $transfer);
    }
}
