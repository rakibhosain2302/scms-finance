<?php

namespace SCMS\Finance\Events;

class TransferPosted extends FinanceEvent
{
    public function name(): string
    {
        return 'finance.transfer.posted';
    }

    public function description(): string
    {
        return "Transfer {$this->subject->number} of {$this->subject->amount} posted";
    }
}
