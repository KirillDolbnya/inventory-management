<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
