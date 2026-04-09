<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuperappPurposeCode extends Model
{
    protected $connection = 'superapp';
    protected $table = 'm_car_driver_request_purposes';
    protected $guarded = [];
}
