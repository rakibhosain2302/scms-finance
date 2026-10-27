<?php

namespace SCMS\Finance\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Nit\Master\Support\Branches;
use SCMS\Finance\Contracts\BranchAccess;

/**
 * nit-master's branches: what the user picked in the header, else every branch assigned to them.
 * Super admins, and users without any branch, are not limited (nit-master's rule).
 */
class NitBranchAccess implements BranchAccess
{
    public function visibleIds(?Authenticatable $user): ?array
    {
        return Branches::visibleIds($user);
    }

    public function canAccess(?Authenticatable $user, int|string|null $branchId): bool
    {
        if ($user === null || $branchId === null || $branchId === '') {
            return false;
        }

        // Every branch the user works in, whichever one is picked in the header.
        return Branches::forUser($user)->contains('id', (int) $branchId);
    }

    public function defaultFor(?Authenticatable $user): ?int
    {
        $id = Branches::defaultFor($user);
        if ($id !== null) {
            return $id;
        }

        $branches = Branches::forUser($user);

        return $branches->count() === 1 ? (int) $branches->first()->id : null;
    }

    public function options(?Authenticatable $user): array
    {
        return Branches::forUser($user)->pluck('name', 'id')->all();
    }
}
