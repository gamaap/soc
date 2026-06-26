<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class DraftBreakEntry extends Model
{
    protected $fillable = [
        'date',
        'name',
        'department',
        'section',
        'actual_arrival',
        'minutes_late',
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

    public function getFormattedTimeAttribute()
    {
        return Carbon::parse($this->actual_arrival)->format('H:i');
    }
}
