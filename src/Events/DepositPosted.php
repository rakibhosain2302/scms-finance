<?php

namespace SCMS\Finance\Events;

class DepositPosted extends FinanceEvent
{
    public function name(): string
    {
        return 'finance.deposit.posted';
    }

    public function description(): string
    {
        return "Deposit {$this->subject->number} of {$this->subject->amount} posted";
    }
}
