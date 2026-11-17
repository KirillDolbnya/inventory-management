<?php

namespace App\UseCases\Event\AddReservation;

readonly class AddReservationInput
{
    /**
     * @param list<array{
     * inventory_placement_id: int,
     * quantity: int
     * }> $reservations
     */
    public function __construct(
        public int $eventId,
        public array $reservations
    ) {}
}
