<?php

namespace Database\Factories;

use App\Models\Cell;
use App\Models\Rack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cell>
 */
class CellFactory extends Factory
{
    protected $model = Cell::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rack_id' => Rack::factory(),
            'level' => function (array $attributes) {
                $rack = Rack::query()->findOrFail((int) $attributes['rack_id']);

                return $this->faker->numberBetween(1, $rack->levels_count);
            },
            'position' => function (array $attributes) {
                $rack = Rack::query()->findOrFail((int) $attributes['rack_id']);

                return $this->faker->numberBetween(1, $rack->cells_per_level);
            },
            'number' => function (array $attributes) {
                $rack = Rack::query()->findOrFail((int) $attributes['rack_id']);
                $level = $attributes['level'];
                $position = $attributes['position'];
                $cellsPerLevel = $rack->cells_per_level;

                return ($level - 1) * $cellsPerLevel + $position;
            },
        ];
    }
}
