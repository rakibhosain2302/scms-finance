<?php

namespace SCMS\Finance\Events;

class JournalPosted extends FinanceEvent
{
    public function name(): string
    {
        return 'finance.journal.posted';
    }

    public function description(): string
    {
        return "Journal entry {$this->subject->number} posted";
    }
}
