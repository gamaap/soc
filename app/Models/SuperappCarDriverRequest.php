<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuperappCarDriverRequest extends Model
{
    protected $connection = 'superapp';
    protected $table = 'rrs.r_car_driver_requests';
    protected $guarded = [];

    public function drivers()
    {
        return $this->hasMany(SuperappCarDriverRequestDriver::class, 'reff_code', 'code');
    }

    public function car()
    {
        return $this->belongsTo(SuperappCarDriverRequestCar::class,  'code', 'reff_code');
    }

    public function purpose()
    {
        return $this->belongsTo(SuperappPurposeCode::class, 'purpose_code', 'code');
    }

    public function destinations()
    {
        return $this->hasMany(SuperappCarDriverRequestDestination::class, 'reff_code', 'code');
    }
}
