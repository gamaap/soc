<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuperappLeaveApprovement extends Model
{
    protected $connection = 'superapp';

    protected $table = 'pms.p_approvements';

    protected $guarded = [];

    public function approver()
    {
        return $this->belongsTo(SuperappPmsEmployee::class, 'approver_id');
    }
}
