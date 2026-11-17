<?php

namespace App\UseCases\Event\Update;

use Carbon\Carbon;

readonly class UpdateEventOutput
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public Carbon $startsAt,
        public Carbon $endsAt,
    ) {}
}
