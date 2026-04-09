<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Visitor extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date' => 'date'
    ];

    public function getFormattedDateAttribute()
    {
        return Carbon::parse($this->date)->format('d/m/Y');
    }

    public function getEntryTimeAttribute($value)
    {
        return $value ? Carbon::parse($value)->format('H:i') : null;
    }

    public function getExitTimeAttribute($value)
    {
        return $value ? Carbon::parse($value)->format('H:i') : null;
    }
}
