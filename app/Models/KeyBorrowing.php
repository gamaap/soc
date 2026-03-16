<?php

namespace App\Models;

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
        return $this->belongsTo(VehicleKey::class);
    }
}
