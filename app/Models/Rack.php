<?php

namespace App\Models;

use Database\Factories\RackFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rack extends Model
{
    /**
     * @use HasFactory<RackFactory>
     */
    use HasFactory;

    protected $casts = [
        'warehouse_id' => 'integer',
        'levels_count' => 'integer',
        'cells_per_level' => 'integer',
    ];

    protected $fillable = [
        'code',
        'warehouse_id',
        'levels_count',
        'cells_per_level',
    ];

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return HasMany<Cell, $this>
     */
    public function cells(): HasMany
    {
        return $this->hasMany(Cell::class);
    }
}
