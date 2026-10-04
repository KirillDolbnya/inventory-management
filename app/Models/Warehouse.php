<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    protected $fillable = [
        'name',
        'address',
        'fias_id',
        'latitude',
        'longitude',
    ];

    /**
     * @return HasMany<Rack, $this>
     */
    public function racks(): HasMany
    {
        return $this->hasMany(Rack::class);
    }
}
