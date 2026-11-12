<?php

namespace App\UseCases\Event\Create;

readonly class CreateEventReservationOutput
{
    public function __construct(
        public int $id,
        public int $inventoryId,
        public int $cellId,
        public int $quantity,
    ) {}
}
