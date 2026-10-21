<?php

namespace App\Models;

use Database\Factories\WarehouseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    /**
     * @use HasFactory<WarehouseFactory>
     */
    use HasFactory;

    const string FORMAT = 'glb';

    const string UNIT = 'm';

    const string ORIGIN = 'floor-center';

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
        'model_path',
    ];

    /**
     * @return HasMany<Rack, $this>
     */
    public function racks(): HasMany
    {
        return $this->hasMany(Rack::class);
    }
}
