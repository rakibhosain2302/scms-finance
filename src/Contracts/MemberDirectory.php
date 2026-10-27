<?php

namespace SCMS\Finance\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * The members that own savings accounts and loans. They live in another package (scms-core);
 * finance only keeps their ids.
 */
interface MemberDirectory
{
    /** Whether a member model is configured at all. */
    public function available(): bool;

    public function find(int|string|null $id): ?Model;

    /** "M-0012 · Rahima Begum" */
    public function label(?Model $member): string;

    /** The member's branch, or null when members are not tied to a branch. */
    public function branchId(Model $member): ?int;

    /**
     * Members for a select, limited to the branches given (null = any), as id => label.
     *
     * @param  list<int>|null  $branchIds
     * @return array<int|string, string>
     */
    public function options(?array $branchIds, ?string $term = null, int $limit = 500): array;

    /**
     * Labels of several members at once (one query), as id => label.
     *
     * @param  iterable<int|string>  $ids
     * @return array<int|string, string>
     */
    public function labels(iterable $ids): array;
}
