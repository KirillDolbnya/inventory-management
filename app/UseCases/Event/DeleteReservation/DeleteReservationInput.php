<?php

namespace App\UseCases\Event\DeleteReservation;

readonly class DeleteReservationInput
{
    public function __construct(
        public int $eventId,
        public int $reservationId,
    ) {}
}
