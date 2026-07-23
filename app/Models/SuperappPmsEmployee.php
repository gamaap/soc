<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuperappPmsEmployee extends Model
{
    protected $connection = 'superapp';

    protected $table = 'm_employees';

    protected $guarded = [];

    public function department()
    {
        return $this->belongsTo(SuperappPmsDepartment::class, 'department_id');
    }

    public function position()
    {
        return $this->belongsTo(SuperappPmsPosition::class, 'position_id');
    }
}
