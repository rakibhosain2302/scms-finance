<?php

namespace SCMS\Finance\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use SCMS\Finance\Contracts\BranchAccess;

/**
 * Policies check the ability and the branch of the record. Note: nit-master's Gate::before lets
 * super admins through every check; the services still enforce the business rules for them.
 */
abstract class FinancePolicy
{
    protected function inBranch(Authenticatable $user, Model $record): bool
    {
        return app(BranchAccess::class)->canAccess($user, $record->getAttribute('branch_id'));
    }

    protected function allows(Authenticatable $user, string $ability, Model $record): bool
    {
        return $user->can($ability) && $this->inBranch($user, $record);
    }
}
