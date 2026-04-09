<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuperappCarDriverRequestHistory extends Model
{
    protected $connection = 'superapp';
    protected $table = 'rrs.r_car_driver_request_histories';
    protected $guarded = [];
}
