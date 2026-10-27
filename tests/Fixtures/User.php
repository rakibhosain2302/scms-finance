<?php

namespace SCMS\Finance\Tests\Fixtures;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Nit\Master\Http\Traits\NitAuthorization;

/** The application's user, as an app using nit-master defines it (the header lists its notifications). */
class User extends Authenticatable
{
    use NitAuthorization, Notifiable, SoftDeletes;

    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password'];
}
