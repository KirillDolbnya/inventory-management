<?php

namespace App\UseCases\Event\AddReservation;

readonly class AddReservationOutput
{
    /**
     * @param list<array{
     *     id: int,
     *     event_id: int,
     *     inventory_id: int,
     *     cell_id: int,
     *     quantity: int
     * }> $reservations
     */
    public function __construct(
        public array $reservations
    ) {}
}
