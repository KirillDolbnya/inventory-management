<?php

namespace App\UseCases\Event\QueryDetail;

readonly class QueryDetailEventInput
{
    public function __construct(
        public int $eventId,
    ) {}
}
