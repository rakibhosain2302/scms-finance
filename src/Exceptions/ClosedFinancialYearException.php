<?php

namespace SCMS\Finance\Exceptions;

class ClosedFinancialYearException extends FinanceException
{
    public function __construct(string $message = '')
    {
        parent::__construct($message !== '' ? $message : __('No open financial year contains this date.'));
    }
}
