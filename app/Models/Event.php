<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    protected $fillable = [
        'name',
        'description',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /**
     * @return HasMany<EventInventoryReservation, $this>
     */
    public function eventInventoryReservations(): HasMany
    {
        return $this->hasMany(EventInventoryReservation::class);
    }
}
