<?php

namespace App\UseCases\Event\Create;

use Carbon\Carbon;

readonly class CreateEventInput
{
    /**
     * @param  list<CreateEventReservationInput>  $reservations
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public Carbon $startsAt,
        public Carbon $endsAt,
        public array $reservations
    ) {}
}
