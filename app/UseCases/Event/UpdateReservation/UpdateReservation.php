<?php

namespace App\UseCases\Event\UpdateReservation;

use App\Exceptions\EventNotFoundException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InventoryNotFoundException;
use App\Exceptions\InventoryPlacementNotFoundException;
use App\Exceptions\InventoryReservationConflictException;
use App\Exceptions\ReservationNotFoundException;
use App\Repositories\EventRepository;
use App\Repositories\InventoryRepository;
use App\Services\Inventory\InventoryReservationAvailabilityCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UpdateReservation
{
    public function __construct(
        private readonly InventoryRepository $inventoryRepository,
        private readonly EventRepository $eventRepository,
        private readonly InventoryReservationAvailabilityCalculator $inventoryReservationAvailabilityCalculator
    ) {}

    /**
     * @throws EventNotFoundException
     * @throws ReservationNotFoundException
     * @throws \Throwable
     */
    public function __invoke(UpdateReservationInput $input): UpdateReservationOutput
    {
        $event = $this->eventRepository->getEventById($input->eventId);

        if ($event === null) {
            throw new EventNotFoundException;
        }

        $reservation = $event->eventInventoryReservations->firstWhere('id', $input->reservationId);

        if ($reservation === null) {
            throw new ReservationNotFoundException;
        }

        $inventory = $this->inventoryRepository->getById($reservation->inventory_id);

        if ($inventory === null) {
            throw new InventoryNotFoundException;
        }

        $placement = $this->inventoryRepository->getPlacementByInventoryAndCell($reservation->inventory_id, $reservation->cell_id);

        if ($placement === null) {
            throw new InventoryPlacementNotFoundException;
        }

        return DB::transaction(function () use ($input, $event, $reservation, $inventory, $placement) {
            $inventory = $this->inventoryRepository->getByIdForUpdate($reservation->inventory_id);
            if ($inventory === null) {
                throw new InventoryNotFoundException;
            }

            $placement = $this->inventoryRepository->getPlacementByIdForUpdate($placement->id, $inventory->id);
            if ($placement === null) {
                throw new InventoryPlacementNotFoundException;
            }

            $event = $this->eventRepository->getEventByIdForUpdate($event->id);
            if ($event === null) {
                throw new EventNotFoundException;
            }

            $reservation = $event->eventInventoryReservations->firstWhere('id', $input->reservationId);
            if ($reservation === null) {
                throw new ReservationNotFoundException;
            }

            $startsAt = Carbon::parse($event->starts_at);
            $endsAt = Carbon::parse($event->ends_at);
            $requestedQuantity = $input->quantity;
            $physicalQuantity = $placement->quantity;

            if ($placement->quantity < $requestedQuantity) {
                throw new InsufficientStockException('Недостаточно инвентаря в ячейке '.$placement->cell_id.' для размещении '.$placement->id);
            }

            $overlappingEvents = $this->eventRepository->getOverlappingByInterval($startsAt, $endsAt, $inventory->id, $placement->cell_id, $event->id);

            $minimumAvailableQuantity = ($this->inventoryReservationAvailabilityCalculator)($overlappingEvents, $physicalQuantity);

            if (($minimumAvailableQuantity - $requestedQuantity) < 0) {
                throw new InventoryReservationConflictException(sprintf(
                    'Невозможно обновнить зарезервированный инвентарь. В период с %s по %s на складе не будет хватать свободных позиций.',
                    $startsAt->toDateTimeString(),
                    $endsAt->toDateTimeString()
                ));
            }

            $reservation = $this->eventRepository->updateReservation($reservation, $input->quantity);

            return new UpdateReservationOutput($reservation->id, $reservation->event_id, $reservation->inventory_id, $reservation->cell_id, $reservation->quantity);
        });
    }
}
