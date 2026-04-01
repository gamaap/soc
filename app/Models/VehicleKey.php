<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleKey extends Model
{
    protected $guarded = [];

    public function borrowings(): HasMany
    {
        return $this->hasMany(KeyBorrowing::class);
    }

    public function getAvailableAttribute()
    {
        $borrowed = $this->borrowings()
            ->whereNull('returned_at')
            ->count();

        return $this->total_keys - $borrowed;
    }
}
