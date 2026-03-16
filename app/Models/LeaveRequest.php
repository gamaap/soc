<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    protected $guarded = [];

    public function getFormattedDateAttribute()
    {
        return Carbon::parse($this->date)->format('d/m/Y');
    }

    public function getFormattedTimeAttribute()
    {
        if (! $this->permitted_end_time) {
            return Carbon::parse($this->permitted_start_time)->format('H:i');
        }

        return Carbon::parse($this->permitted_start_time)->format('H:i')
        . ' - ' .
        Carbon::parse($this->permitted_end_time)->format('H:i');
    }
}
