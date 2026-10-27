<?php

namespace SCMS\Finance\Models;

class Income extends Voucher
{
    protected $table = 'finance_incomes';

    public static function partyColumn(): string
    {
        return 'payer';
    }

    public static function kind(): string
    {
        return 'income';
    }
}
