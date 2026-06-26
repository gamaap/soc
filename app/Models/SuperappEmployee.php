<?php

namespace App\Models;

use App\Models\SuperappDepartment;
use App\Models\SuperappSection;
use Illuminate\Database\Eloquent\Model;

class SuperappEmployee extends Model
{
    protected $connection = 'superapp';
    protected $table = 'users';
    protected $guarded = [];

    public function department()
    {
        return $this->belongsTo(SuperappDepartment::class, 'departement_id');
    }

    public function section()
    {
        return $this->belongsTo(SuperappSection::class, 'section_id');
    }
}
