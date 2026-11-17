<?php

namespace App\UseCases\Event\Update;

use Carbon\Carbon;

readonly class UpdateEventInput
{
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $description,
        public bool $descriptionProvided,
        public ?Carbon $startsAt,
        public ?Carbon $endsAt
    ) {}
}
