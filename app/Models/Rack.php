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
        'position_x' => 'float',
        'position_y' => 'float',
        'position_z' => 'float',
        'rotation_y_deg' => 'float',
        'width' => 'float',
        'depth' => 'float',
        'height' => 'float',
    ];

    protected $fillable = [
        'code',
        'warehouse_id',
        'levels_count',
        'cells_per_level',
        'position_x',
        'position_y',
        'position_z',
        'rotation_y_deg',
        'width',
        'depth',
        'height',
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
