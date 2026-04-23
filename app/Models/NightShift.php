<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class NightShift extends Model
{
    protected $fillable = [
        'date',
        'name',
        'department',
        'division',
        'check_in_time',
        'check_out_time',
        'photo',
    ];
    
    protected $casts = [
        'date' => 'date'
    ];

    public function getFormattedDateAttribute()
    {
        return Carbon::parse($this->date)->format('d/m/Y');
    }

    public function getCheckInTimeAttribute($value)
    {
        return $value ? Carbon::parse($value)->format('H:i') : null;
    }

    public function getCheckOutTimeAttribute($value)
    {
        return $value ? Carbon::parse($value)->format('H:i') : null;
    }
}
