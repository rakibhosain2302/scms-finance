<?php

namespace SCMS\Finance\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Which branches a user may see and post to. Bound to nit-master's Branches when that package
 * is installed (SCMS\Finance\Support\NitBranchAccess); bind your own to use another branch system.
 */
interface BranchAccess
{
    /**
     * Branch ids whose records the user sees right now; null = no limit.
     *
     * @return list<int>|null
     */
    public function visibleIds(?Authenticatable $user): ?array;

    /** Whether the user may read and write records of the branch. */
    public function canAccess(?Authenticatable $user, int|string|null $branchId): bool;

    /** Branch preselected on a new record (the one picked in the header), or null. */
    public function defaultFor(?Authenticatable $user): ?int;

    /**
     * Branches the user may pick on a form, as id => name.
     *
     * @return array<int, string>
     */
    public function options(?Authenticatable $user): array;
}
