<?php

namespace SCMS\Finance\Exceptions;

class InsufficientBalanceException extends FinanceException
{
    public function __construct(string $message = '')
    {
        parent::__construct($message !== '' ? $message : __('The balance is not enough.'));
    }
}
