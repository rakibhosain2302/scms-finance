<?php

namespace SCMS\Finance\Models;

class Expense extends Voucher
{
    protected $table = 'finance_expenses';

    public static function partyColumn(): string
    {
        return 'payee';
    }

    public static function kind(): string
    {
        return 'expense';
    }
}
