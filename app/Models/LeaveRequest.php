<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'will_not_return' => 'boolean',
            'is_full_day' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    public function getFormattedDateAttribute(): string
    {
        return Carbon::parse($this->date)->format('d/m/Y');
    }

    public function getFormattedTimeAttribute(): string
    {
        if ($this->is_full_day || ! $this->permitted_start_time) {
            return 'Full Day';
        }

        if (! $this->permitted_end_time) {
            return Carbon::parse($this->permitted_start_time)->format('H:i');
        }

        return Carbon::parse($this->permitted_start_time)->format('H:i')
        .' - '.
        Carbon::parse($this->permitted_end_time)->format('H:i');
    }
}
