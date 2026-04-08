<?php

namespace App\Models;

use App\Models\DraftDeliveryEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DraftDeliveryItemsEntry extends Model
{
    protected $guarded = [];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(DraftDeliveryEntry::class, 'delivery_id');
    }
}
