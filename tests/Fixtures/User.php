<?php

namespace SCMS\Finance\Tests\Fixtures;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Nit\Master\Http\Traits\NitAuthorization;

/** The application's user, as an app using nit-master defines it. */
class User extends Authenticatable
{
    use NitAuthorization, SoftDeletes;

    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password'];
}
