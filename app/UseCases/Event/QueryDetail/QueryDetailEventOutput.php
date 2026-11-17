<?php

namespace App\UseCases\Event\QueryDetail;

use Carbon\Carbon;

readonly class QueryDetailEventOutput
{
    /**
     * @param  list<QueryDetailEventReservationOutput>  $reservations
     */
    public function __construct(
        public int $eventId,
        public string $name,
        public ?string $description,
        public Carbon $startsAt,
        public Carbon $endsAt,
        public array $reservations,
    ) {}
}
