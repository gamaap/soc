<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuperappLeaveRequest extends Model
{
    protected $connection = 'superapp';

    protected $table = 'pms.p_requests';

    protected $guarded = [];

    public function employee()
    {
        return $this->belongsTo(SuperappPmsEmployee::class, 'requested_by');
    }

    public function subType()
    {
        return $this->belongsTo(SuperappRequestSubType::class, 'request_sub_type_id');
    }

    public function approvements()
    {
        return $this->hasMany(SuperappLeaveApprovement::class, 'request_id');
    }
}
