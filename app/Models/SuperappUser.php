<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuperappUser extends Model
{
    protected $connection = 'superapp';
    protected $table = 'users';
}
