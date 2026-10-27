<?php

namespace SCMS\Finance\Models;

use Illuminate\Database\Eloquent\Model;

class Sequence extends Model
{
    protected $table = 'finance_sequences';

    protected $fillable = ['series', 'last_number'];
}
