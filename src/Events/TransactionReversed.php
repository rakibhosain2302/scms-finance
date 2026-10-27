<?php

namespace SCMS\Finance\Events;

use Illuminate\Database\Eloquent\Model;
use SCMS\Finance\Models\JournalEntry;

/** A posted document or journal entry was reversed; $reversal is the new, mirrored entry. */
class TransactionReversed extends FinanceEvent
{
    public function __construct(Model $subject, public readonly JournalEntry $reversal, public readonly string $reason, int|string|null $userId = null)
    {
        parent::__construct($subject, $userId);
    }

    public function name(): string
    {
        return 'finance.transaction.reversed';
    }

    public function description(): string
    {
        $label = $this->subject->number ?? $this->subject->loan_number ?? '#'.$this->subject->getKey();

        return "Reversed {$label} by {$this->reversal->number}: {$this->reason}";
    }
}
