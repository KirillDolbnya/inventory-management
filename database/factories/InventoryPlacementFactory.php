<?php

namespace Database\Factories;

use App\Models\Cell;
use App\Models\Inventory;
use App\Models\InventoryPlacement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryPlacement>
 */
class InventoryPlacementFactory extends Factory
{
    protected $model = InventoryPlacement::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventory_id' => Inventory::factory(),
            'cell_id' => Cell::factory(),
            'quantity' => 1,
        ];
    }
}
