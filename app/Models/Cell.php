<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cell extends Model
{
    protected $fillable = [
        'number',
        'rack_id',
        'level',
        'position',
    ];

    /**
     * @return BelongsTo<Rack, $this>
     */
    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }

    /**
     * @return HasMany<InventoryPlacement, $this>
     */
    public function inventoryPlacements(): HasMany
    {
        return $this->hasMany(InventoryPlacement::class);
    }
}
