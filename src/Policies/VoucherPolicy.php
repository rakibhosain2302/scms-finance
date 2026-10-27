<?php

namespace SCMS\Finance\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use SCMS\Finance\Enums\EntryStatus;
use SCMS\Finance\Models\Voucher;
use SCMS\Finance\Support\Abilities;
use SCMS\Finance\Support\MakerChecker;

/** Income and expense vouchers: finance_income_* / finance_expense_* abilities. */
class VoucherPolicy extends FinancePolicy
{
    public function view(Authenticatable $user, Voucher $voucher): bool
    {
        return $this->allows($user, $this->ability($voucher, 'view'), $voucher);
    }

    public function update(Authenticatable $user, Voucher $voucher): bool
    {
        return $voucher->status === EntryStatus::Draft && $this->allows($user, $this->ability($voucher, 'create'), $voucher);
    }

    public function post(Authenticatable $user, Voucher $voucher): bool
    {
        return $voucher->status === EntryStatus::Draft && $this->allows($user, $this->ability($voucher, 'post'), $voucher) && MakerChecker::mayPost($user, $voucher);
    }

    public function reverse(Authenticatable $user, Voucher $voucher): bool
    {
        return $voucher->status === EntryStatus::Posted && $this->allows($user, Abilities::REVERSE, $voucher);
    }

    private function ability(Voucher $voucher, string $verb): string
    {
        return 'finance_'.$voucher::kind().'_'.$verb;
    }
}
