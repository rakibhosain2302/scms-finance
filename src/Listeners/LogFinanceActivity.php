<?php

namespace SCMS\Finance\Listeners;

use SCMS\Finance\Events\FinanceEvent;

/** Writes finance events to nit-master's activity log, when that package is installed. */
class LogFinanceActivity
{
    public function handle(FinanceEvent $event): void
    {
        if (! class_exists(\Nit\Master\Support\Activity::class)) {
            return;
        }

        \Nit\Master\Support\Activity::log($event->name(), $event->description(), $event->subject, [], $event->userId);
    }
}
