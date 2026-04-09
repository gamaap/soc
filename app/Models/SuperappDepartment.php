<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuperappDepartment extends Model
{
    protected $connection = 'superapp';
    protected $table = 'm_departements';
}
