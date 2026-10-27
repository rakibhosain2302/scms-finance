<?php

namespace SCMS\Finance\Events;

class ExpensePosted extends FinanceEvent
{
    public function name(): string
    {
        return 'finance.expense.posted';
    }

    public function description(): string
    {
        return "Expense {$this->subject->number} of {$this->subject->amount} posted";
    }
}
