<?php

namespace App\Models;

use Database\Factories\InventoryPlacementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryPlacement extends Model
{
    /**
     * @use HasFactory<InventoryPlacementFactory>
     */
    use HasFactory;

    protected $fillable = [
        'inventory_id',
        'cell_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * @return BelongsTo<Inventory, $this>
     */
    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    /**
     * @return BelongsTo<Cell, $this>
     */
    public function cell(): BelongsTo
    {
        return $this->belongsTo(Cell::class);
    }
}
