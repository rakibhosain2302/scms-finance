<?php

namespace SCMS\Finance\Events;

class WithdrawalPosted extends FinanceEvent
{
    public function name(): string
    {
        return 'finance.withdrawal.posted';
    }

    public function description(): string
    {
        return "Withdrawal {$this->subject->number} of {$this->subject->amount} posted";
    }
}
