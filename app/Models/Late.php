<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Late extends Model
{
    protected $guarded = [];

    public function getFormattedDateAttribute()
    {
        return Carbon::parse($this->date)->format('d/m/Y');
    }

    public function getFormattedTimeAttribute()
    {
        return Carbon::parse($this->actual_arrival)->format('H:i');
    }
}
