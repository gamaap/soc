<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuperappCarDriverRequestDriver extends Model
{
    protected $connection = 'superapp';
    protected $table = 'rrs.r_car_driver_request_drivers';
    protected $guarded = [];

    public function driver()
    {
        return $this->belongsTo(SuperappDriver::class, 'driver_code', 'code');
    }
}
