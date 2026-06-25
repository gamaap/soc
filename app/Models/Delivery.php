<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    protected $guarded = [];

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryItems::class);
    }

    public function entryItems(): HasMany
    {
        return $this->hasMany(DeliveryItems::class)->where('direction', 'in');
    }

    public function exitItems(): HasMany
    {
        return $this->hasMany(DeliveryItems::class)->where('direction', 'out');
    }

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
