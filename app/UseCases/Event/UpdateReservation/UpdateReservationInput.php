<?php

namespace App\UseCases\Event\UpdateReservation;

readonly class UpdateReservationInput
{
    public function __construct(
        public int $eventId,
        public int $reservationId,
        public int $quantity,
    ) {}
}
