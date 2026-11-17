<?php

namespace App\UseCases\Event\Create;

use Carbon\Carbon;

readonly class CreateEventOutput
{
    /**
     * @param  list<CreateEventReservationOutput>  $reservations
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public Carbon $startsAt,
        public Carbon $endsAt,
        public array $reservations,
    ) {}
}
