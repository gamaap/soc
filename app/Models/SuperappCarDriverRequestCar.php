<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuperappCarDriverRequestCar extends Model
{
    protected $connection = 'superapp';
    protected $table = 'rrs.r_car_driver_request_cars';
    protected $guarded = [];
}
