<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeyBorrowing extends Model
{
    protected $guarded = [];

    public function vehicleKey(): BelongsTo
    {
        return $this->belongsTo(VehicleKey::class);
    }

    public function facilityKey(): BelongsTo
    {
        return $this->belongsTo(FacilityKey::class);
    }

    public function boxKey(): BelongsTo
    {
        return $this->belongsTo(BoxKey::class);
    }

    public function getBorrowedAtAttribute($value)
    {
        return $value ? Carbon::parse($value)->format('H:i') : null;
    }

    public function getReturnedAtAttribute($value)
    {
        return $value ? Carbon::parse($value)->format('H:i') : null;
    }
}
