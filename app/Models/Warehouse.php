<?php

namespace App\Models;

use Database\Factories\WarehouseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    /**
     * @use HasFactory<WarehouseFactory>
     */
    use HasFactory;

    use SoftDeletes;

    const string FORMAT = 'glb';

    const string UNIT = 'm';

    const string ORIGIN = 'floor-center';

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'walls' => 'array',
    ];

    protected $fillable = [
        'name',
        'address',
        'fias_id',
        'latitude',
        'longitude',
        'model_path',
        'walls',
    ];

    /**
     * @return HasMany<Rack, $this>
     */
    public function racks(): HasMany
    {
        return $this->hasMany(Rack::class);
    }
}
