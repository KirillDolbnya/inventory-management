<?php

namespace App\UseCases\Event\Create;

readonly class CreateEventReservationInput
{
    public function __construct(
        public int $inventoryPlacementId,
        public int $quantity,
    ) {}
}
