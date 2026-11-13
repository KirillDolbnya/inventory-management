<?php

namespace Database\Factories;

use App\Models\Rack;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rack>
 */
class RackFactory extends Factory
{
    protected $model = Rack::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => mb_strtoupper($this->faker->unique()->bothify('ST-?#')),
            'warehouse_id' => Warehouse::factory(),
            'levels_count' => $this->faker->numberBetween(1, 4),
            'cells_per_level' => $this->faker->numberBetween(1, 6),
        ];
    }
}
