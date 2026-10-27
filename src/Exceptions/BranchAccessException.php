<?php

namespace SCMS\Finance\Exceptions;

class BranchAccessException extends FinanceException
{
    public function __construct(string $message = '')
    {
        parent::__construct($message !== '' ? $message : __('You cannot use records of this branch.'));
    }
}
