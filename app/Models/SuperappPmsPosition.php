<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuperappPmsPosition extends Model
{
    protected $connection = 'superapp';

    protected $table = 'm_positions';

    protected $guarded = [];
}
