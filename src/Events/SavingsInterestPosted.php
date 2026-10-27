<?php

namespace SCMS\Finance\Events;

class SavingsInterestPosted extends FinanceEvent
{
    public function name(): string
    {
        return 'finance.savings_interest.posted';
    }

    public function description(): string
    {
        return "Interest of {$this->subject->total} posted on {$this->subject->accounts_count} savings accounts ({$this->subject->period_from->toDateString()} to {$this->subject->period_to->toDateString()})";
    }
}
