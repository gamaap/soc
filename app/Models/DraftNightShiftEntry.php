<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class DraftNightShiftEntry extends Model
{
    protected $fillable = [
        'date',
        'name',
        'department',
        'division',
        'check_in_time',
        'check_out_time',
        'photo',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

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
