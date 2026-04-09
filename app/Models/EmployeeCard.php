<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeCard extends Model
{
    protected $connection = 'rfid';
    protected $table = 'employees';
    protected $guarded = [];

    public function department()
    {
        return $this->belongsTo(SuperappDepartment::class, 'departement_id');
    }
}
