<?php

namespace App\UseCases\Event\DeleteReservation;

use App\Exceptions\EventNotFoundException;
use App\Exceptions\ReservationNotFoundException;
use App\Repositories\EventRepository;
use Illuminate\Support\Facades\DB;

class DeleteReservation
{
    public function __construct(
        private readonly EventRepository $eventRepository,
    ) {}

    /**
     * @throws EventNotFoundException
     * @throws ReservationNotFoundException
     * @throws \Throwable
     */
    public function __invoke(DeleteReservationInput $input): void
    {
        DB::transaction(function () use ($input): void {
            $event = $this->eventRepository->getEventByIdForUpdate($input->eventId);

            if ($event === null) {
                throw new EventNotFoundException;
            }

            $reservation = $event->eventInventoryReservations->firstWhere('id', $input->reservationId);

            if ($reservation === null) {
                throw new ReservationNotFoundException;
            }

            $this->eventRepository->deleteReservation($reservation);
        });
    }
}
