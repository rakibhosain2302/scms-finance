<?php

namespace SCMS\Finance\Exceptions;

class UnbalancedJournalException extends FinanceException
{
    public function __construct(string $message = '')
    {
        parent::__construct($message !== '' ? $message : __('Debits and credits must be equal.'));
    }
}
