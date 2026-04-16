<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DraftVehiclePassEntry extends Model
{
    protected $guarded = [];
    protected $table = 'draft_vehicle_passes';

    public function superappCarDriverRequest(): BelongsTo
    {
        return $this->belongsTo(SuperappCarDriverRequest::class, 'superapp_car_driver_request_id');
    }
}