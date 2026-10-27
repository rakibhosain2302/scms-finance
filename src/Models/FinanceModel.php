<?php

namespace SCMS\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use SCMS\Finance\Models\Concerns\HasMoneyAttributes;

/** Base of the finance models. */
abstract class FinanceModel extends Model
{
    use HasMoneyAttributes;

    /** @var list<string> attributes cast to exact decimal strings */
    protected array $money = [];
}
