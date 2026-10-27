<?php

namespace SCMS\Finance\Events;

class IncomePosted extends FinanceEvent
{
    public function name(): string
    {
        return 'finance.income.posted';
    }

    public function description(): string
    {
        return "Income {$this->subject->number} of {$this->subject->amount} posted";
    }
}
