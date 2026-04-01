<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuperappCarDriverRequestDestination extends Model
{
    protected $connection = 'superapp';
    protected $table = 'rrs.r_car_driver_request_destinations';
    protected $guarded = [];
}
