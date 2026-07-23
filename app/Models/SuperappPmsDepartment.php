<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuperappPmsDepartment extends Model
{
    protected $connection = 'superapp';

    protected $table = 'm_pms_departments';

    protected $guarded = [];
}
