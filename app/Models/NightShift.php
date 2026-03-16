<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class NightShift extends Model
{
    protected $guarded = [];
    
    protected $casts = [
        'date' => 'date'
    ];

    public function getFormattedDateAttribute()
    {
        return Carbon::parse($this->date)->format('d/m/Y');
    }
}
