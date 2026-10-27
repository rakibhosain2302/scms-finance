<?php

namespace SCMS\Finance\Events;

class LoanDisbursed extends FinanceEvent
{
    public function name(): string
    {
        return 'finance.loan.disbursed';
    }

    public function description(): string
    {
        return "Loan {$this->subject->loan_number} of {$this->subject->principal} disbursed";
    }
}
