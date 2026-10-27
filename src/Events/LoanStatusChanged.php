<?php

namespace SCMS\Finance\Events;

use Illuminate\Database\Eloquent\Model;

/** Approved, rejected, cancelled, completed … */
class LoanStatusChanged extends FinanceEvent
{
    public function __construct(Model $subject, public readonly string $from, public readonly string $to, int|string|null $userId = null)
    {
        parent::__construct($subject, $userId);
    }

    public function name(): string
    {
        return 'finance.loan.'.$this->to;
    }

    public function description(): string
    {
        return "Loan {$this->subject->loan_number}: {$this->from} → {$this->to}";
    }
}
