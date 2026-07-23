<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuperappRequestSubType extends Model
{
    protected $connection = 'superapp';

    protected $table = 'm_request_sub_types';

    protected $guarded = [];
}
