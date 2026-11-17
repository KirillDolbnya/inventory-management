<?php

namespace App\UseCases\Event\Delete;

use App\Exceptions\EventNotFoundException;
use App\Repositories\EventRepository;

class DeleteEvent
{
    public function __construct(
        private readonly EventRepository $eventRepository
    ) {}

    /**
     * @throws EventNotFoundException
     */
    public function __invoke(DeleteEventInput $input): void
    {
        $event = $this->eventRepository->getEventById($input->eventId);

        if ($event === null) {
            throw new EventNotFoundException;
        }

        $this->eventRepository->deleteWithReservations($event);
    }
}
