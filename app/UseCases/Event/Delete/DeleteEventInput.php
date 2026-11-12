<?php

namespace App\UseCases\Event\Delete;

readonly class DeleteEventInput
{
    public function __construct(
        public int $eventId
    ) {}
}
