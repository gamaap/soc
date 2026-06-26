<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DraftLateEntry extends Model
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

    public function user(): BelongsTo
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
