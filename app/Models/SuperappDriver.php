<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuperappDriver extends Model
{
    protected $connection = 'superapp';
    protected $table = 'm_drivers';
}
