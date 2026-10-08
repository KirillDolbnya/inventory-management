<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    protected $fillable = [
        'name',
        'description',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * @return HasMany<InventoryPlacement, $this>
     */
    public function inventoryPlacements(): HasMany
    {
        return $this->hasMany(InventoryPlacement::class);
    }
}
