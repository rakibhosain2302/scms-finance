<?php

namespace SCMS\Finance\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;

/**
 * Raised after the posting's transaction commits, so listeners (notifications, SMS, the activity
 * log) never see a posting that was rolled back. Accounting itself never waits on them.
 */
abstract class FinanceEvent implements ShouldDispatchAfterCommit
{
    public function __construct(public readonly Model $subject, public readonly int|string|null $userId = null)
    {
    }

    /** Activity log event name ("finance.deposit.posted"). */
    abstract public function name(): string;

    abstract public function description(): string;
}
