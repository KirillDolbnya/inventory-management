<?php

namespace App\UseCases\Event\UpdateReservation;

readonly class UpdateReservationOutput
{
    public function __construct(
        public int $id,
        public int $eventId,
        public int $inventoryId,
        public int $cellId,
        public int $quantity,
    ) {}
}
