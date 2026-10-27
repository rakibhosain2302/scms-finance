<?php

namespace SCMS\Finance\Events;

class LoanRepaymentPosted extends FinanceEvent
{
    public function name(): string
    {
        return 'finance.loan_repayment.posted';
    }

    public function description(): string
    {
        return "Loan repayment {$this->subject->number} of {$this->subject->amount} posted";
    }
}
