<?php

namespace SCMS\Finance\Exceptions;

class InvalidStateException extends FinanceException
{
    public function __construct(string $message = '')
    {
        parent::__construct($message !== '' ? $message : __('This cannot be done in the current state.'));
    }
}
