<?php

namespace App\UseCases\Event\QueryList;

use Carbon\Carbon;

readonly class QueryListEventItemOutput
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public Carbon $startsAt,
        public Carbon $endsAt,
    ) {}
}
