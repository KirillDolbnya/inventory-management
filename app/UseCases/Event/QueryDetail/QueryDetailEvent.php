<?php

namespace App\UseCases\Event\QueryDetail;

use App\Exceptions\EventNotFoundException;
use App\Repositories\EventRepository;
use Carbon\Carbon;

class QueryDetailEvent
{
    public function __construct(
        private readonly EventRepository $eventRepository
    ) {}

    /**
     * @throws EventNotFoundException
     */
    public function __invoke(QueryDetailEventInput $input): QueryDetailEventOutput
    {
        $event = $this->eventRepository->getByIdWithReservations($input->eventId);

        if ($event === null) {
            throw new EventNotFoundException;
        }

        $reservationOutputs = collect($event->eventInventoryReservations)->map(function ($reservation) {

            $inventoryModel = $reservation->inventory;

            $cellModel = $reservation->cell;

            $rackModel = $cellModel->rack;

            $warehouseModel = $rackModel->warehouse;

            return new QueryDetailEventReservationOutput(
                reservationId: $reservation->id,
                reservationQuantity: $reservation->quantity,

                inventory: new QueryDetailEventInventoryOutput(
                    inventoryId: $inventoryModel->id,
                    name: $inventoryModel->name,
                    quantity: $inventoryModel->quantity,
                ),

                warehouse: new QueryDetailEventWarehouseOutput(
                    warehouseId: (int) $warehouseModel->id,
                    name: (string) $warehouseModel->name
                ),

                rack: new QueryDetailEventRackOutput(
                    rackId: (int) $rackModel->id,
                    code: (string) $rackModel->code
                ),

                cell: new QueryDetailEventCellOutput(
                    cellId: (int) $cellModel->id,
                    number: (int) $cellModel->number,
                    level: (int) $cellModel->level,
                    position: (int) $cellModel->position
                )
            );
        })->all();

        $startsAt = Carbon::parse($event->starts_at);
        $endsAt = Carbon::parse($event->ends_at);

        return new QueryDetailEventOutput(
            eventId: $event->id,
            name: $event->name,
            description: $event->description,
            startsAt: $startsAt,
            endsAt: $endsAt,
            reservations: $reservationOutputs,
        );
    }
}
