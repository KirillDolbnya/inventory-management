<?php

namespace Database\Factories;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = Carbon::instance($this->faker->dateTimeBetween('now', '+1 month'));
        $endsAt = $startsAt->clone()->addHours(2);

        return [
            'name' => $this->faker->name(),
            'description' => $this->faker->text(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ];
    }
}
