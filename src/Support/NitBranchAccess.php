<?php

namespace SCMS\Finance\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Nit\Master\Support\Branches;
use SCMS\Finance\Contracts\BranchAccess;

/**
 * nit-master's branches: what the user picked in the header, else every branch assigned to them.
 * Super admins are not limited. nit-master also leaves users without any branch unlimited; for
 * money that is unsafe (a forgotten assignment would open every branch), so with
 * scms-finance.rules.require_branch such a user sees and touches no branch at all.
 */
class NitBranchAccess implements BranchAccess
{
    public function visibleIds(?Authenticatable $user): ?array
    {
        return $this->unassigned($user) ? [] : Branches::visibleIds($user);
    }

    public function canAccess(?Authenticatable $user, int|string|null $branchId): bool
    {
        if ($user === null || $branchId === null || $branchId === '' || $this->unassigned($user)) {
            return false;
        }

        // Every branch the user works in, whichever one is picked in the header.
        return Branches::forUser($user)->contains('id', (int) $branchId);
    }

    public function defaultFor(?Authenticatable $user): ?int
    {
        if ($this->unassigned($user)) {
            return null;
        }

        $id = Branches::defaultFor($user);
        if ($id !== null) {
            return $id;
        }

        $branches = Branches::forUser($user);

        return $branches->count() === 1 ? (int) $branches->first()->id : null;
    }

    public function options(?Authenticatable $user): array
    {
        return $this->unassigned($user) ? [] : Branches::forUser($user)->pluck('name', 'id')->all();
    }

    /** A staff member (not a super admin) with no branch, while branches are in use and required. */
    private function unassigned(?Authenticatable $user): bool
    {
        return $user !== null
            && config('scms-finance.rules.require_branch', true)
            && Branches::enabled()
            && ! (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin())
            && Branches::assignedIds($user) === [];
    }
}
