<?php

namespace SCMS\Finance\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use SCMS\Finance\Contracts\BranchAccess;

/**
 * Without nit-master: every signed-in user may use every branch of the configured branch model.
 * Bind your own BranchAccess when branches must be limited.
 */
class OpenBranchAccess implements BranchAccess
{
    public function visibleIds(?Authenticatable $user): ?array
    {
        return null;
    }

    public function canAccess(?Authenticatable $user, int|string|null $branchId): bool
    {
        return $user !== null && $branchId !== null && array_key_exists((int) $branchId, $this->options($user));
    }

    public function defaultFor(?Authenticatable $user): ?int
    {
        $options = $this->options($user);

        return count($options) === 1 ? (int) array_key_first($options) : null;
    }

    public function options(?Authenticatable $user): array
    {
        $model = FinanceModels::branch();

        return $model::query()->orderBy('name')->pluck('name', 'id')->all();
    }
}
