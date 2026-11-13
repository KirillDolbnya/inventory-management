<?php

namespace Database\Factories;

use App\Models\Cell;
use App\Models\Event;
use App\Models\EventInventoryReservation;
use App\Models\Inventory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventInventoryReservation>
 */
class EventInventoryReservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'inventory_id' => Inventory::factory(),
            'cell_id' => Cell::factory(),
            'quantity' => 1,
        ];
    }
}
