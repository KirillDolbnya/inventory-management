<?php

namespace App\UseCases\Event\Update;

use App\Exceptions\EventNotFoundException;
use App\Exceptions\InvalidEventDurationException;
use App\Exceptions\InventoryPlacementNotFoundException;
use App\Exceptions\InventoryReservationConflictException;
use App\Models\Event;
use App\Repositories\EventRepository;
use App\Repositories\InventoryRepository;
use App\Services\Inventory\InventoryReservationAvailabilityCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UpdateEvent
{
    public function __construct(
        private readonly InventoryRepository $inventoryRepository,
        private readonly EventRepository $eventRepository,
        private readonly InventoryReservationAvailabilityCalculator $inventoryReservationAvailabilityCalculator,
    ) {}

    /**
     * @throws EventNotFoundException
     * @throws InventoryReservationConflictException
     * @throws \Throwable
     */
    public function __invoke(UpdateEventInput $input): UpdateEventOutput
    {
        $event = $this->eventRepository->getEventById($input->id);

        if ($event === null) {
            throw new EventNotFoundException;
        }

        if ($input->startsAt !== null || $input->endsAt !== null) {
            return $this->updateWithLock($event, $input);
        }

        $data = array_filter([
            'name' => $input->name,
        ], fn ($value) => $value !== null);

        if ($input->descriptionProvided && $input->description !== $event->description) {
            $data['description'] = $input->description;
        }

        $event = $this->eventRepository->update($event, $data);

        return new UpdateEventOutput($event->id, $event->name, $event->description, $event->starts_at, $event->ends_at);
    }

    /**
     * @throws \Throwable
     */
    private function updateWithLock(Event $event, UpdateEventInput $input): UpdateEventOutput
    {
        return DB::transaction(function () use ($event, $input) {
            $inventoryIds = $event->eventInventoryReservations->pluck('inventory_id')->sort()->values()->all();

            $inventories = $this->inventoryRepository->getInventoryByIdsForUpdate($inventoryIds);
            $placements = $this->inventoryRepository->getPlacementByInventoryIdForUpdate($inventoryIds);
            $event = $this->eventRepository->getEventByIdForUpdate($event->id);

            if ($event === null) {
                throw new EventNotFoundException;
            }

            $startsAt = $input->startsAt ?? Carbon::parse($event->starts_at);
            $endsAt = $input->endsAt ?? Carbon::parse($event->ends_at);

            if ($startsAt->timestamp >= $endsAt->timestamp) {
                throw new InvalidEventDurationException;
            }

            foreach ($event->eventInventoryReservations as $reservation) {
                $inventory = $inventories->where('id', $reservation->inventory_id)->first();
                $placement = $placements->where('inventory_id', $inventory->id)->where('cell_id', $reservation->cell_id)->first();

                if ($placement === null) {
                    throw new InventoryPlacementNotFoundException('Размещение товара '.$reservation->inventory_id.' не найдено в ячейке '.$reservation->cell_id);
                }

                $physicalQuantity = $placement->quantity;
                $reservationQuantity = $reservation->quantity;

                $overlappingEvents = $this->eventRepository->getOverlappingByInterval($startsAt, $endsAt, $inventory->id, $placement->cell_id, $event->id);

                $minimumAvailableQuantity = ($this->inventoryReservationAvailabilityCalculator)($overlappingEvents, $physicalQuantity);

                if (($minimumAvailableQuantity - $reservationQuantity) < 0) {
                    throw new InventoryReservationConflictException(sprintf(
                        'Невозможно забронировать инвентарь. В период с %s по %s на складе не будет хватать свободных позиций.',
                        $startsAt->toDateTimeString(),
                        $endsAt->toDateTimeString()
                    ));
                }
            }

            $data = array_filter([
                'name' => $input->name,
                'starts_at' => $input->startsAt,
                'ends_at' => $input->endsAt,
            ], fn ($value) => $value !== null);

            if ($input->descriptionProvided && $input->description !== $event->description) {
                $data['description'] = $input->description;
            }

            $event = $this->eventRepository->update($event, $data);

            return new UpdateEventOutput($event->id, $event->name, $event->description, $event->starts_at, $event->ends_at);
        });
    }
}
