<?php

namespace SCMS\Finance\Events;

class LoanWrittenOff extends FinanceEvent
{
    public function name(): string
    {
        return 'finance.loan.written_off';
    }

    public function description(): string
    {
        return "Loan {$this->subject->loan_number} written off: {$this->subject->write_off_reason}";
    }
}
