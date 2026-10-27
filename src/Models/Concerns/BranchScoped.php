<?php

namespace SCMS\Finance\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use SCMS\Finance\Contracts\BranchAccess;
use SCMS\Finance\Support\FinanceModels;

/**
 * Records of one branch (column branch_id). A signed-in user only finds the records of the
 * branches they see (route model binding included); console commands and super admins see all.
 * The services still check the branch of every record they touch: the scope is a filter, not
 * the authorization.
 */
trait BranchScoped
{
    public static function bootBranchScoped(): void
    {
        static::addGlobalScope('finance_branch', function (Builder $query) {
            $ids = app(BranchAccess::class)->visibleIds(auth()->user());
            if ($ids !== null) {
                $query->whereIn($query->qualifyColumn('branch_id'), $ids ?: [0]);
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(FinanceModels::branch(), 'branch_id');
    }

    public function scopeWithoutBranchScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('finance_branch');
    }

    /** @param  list<int>|null  $ids  null = every branch */
    public function scopeForBranches(Builder $query, ?array $ids): Builder
    {
        return $ids === null ? $query : $query->whereIn($query->qualifyColumn('branch_id'), $ids ?: [0]);
    }
}
