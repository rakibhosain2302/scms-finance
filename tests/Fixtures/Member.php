<?php

namespace SCMS\Finance\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/** Stands in for scms-core's member. */
class Member extends Model
{
    protected $table = 'test_members';

    protected $fillable = ['member_no', 'name', 'branch_id', 'phone'];
}
