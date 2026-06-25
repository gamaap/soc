<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitorItems extends Model
{
    protected $guarded = [];

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }
}
