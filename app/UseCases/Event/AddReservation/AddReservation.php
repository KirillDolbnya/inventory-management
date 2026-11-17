<?php

namespace App\UseCases\Event\AddReservation;

use App\Exceptions\EventNotFoundException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InventoryPlacementNotFoundException;
use App\Exceptions\InventoryReservationConflictException;
use App\Exceptions\ReservationAlreadyExistsException;
use App\Repositories\EventRepository;
use App\Repositories\InventoryRepository;
use App\Services\Inventory\InventoryReservationAvailabilityCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AddReservation
{
    public function __construct(
        private readonly InventoryRepository $inventoryRepository,
        private readonly EventRepository $eventRepository,
        private readonly InventoryReservationAvailabilityCalculator $inventoryReservationAvailabilityCalculator,
    ) {}

    /**
     * @throws EventNotFoundException
     * @throws InventoryPlacementNotFoundException
     * @throws \Throwable
     */
    public function __invoke(AddReservationInput $input): AddReservationOutput
    {
        $event = $this->eventRepository->getEventById($input->eventId);

        if ($event === null) {
            throw new EventNotFoundException;
        }

        $placementIds = collect($input->reservations)->pluck('inventory_placement_id')->sort()->values()->all();

        $placements = $this->inventoryRepository->getPlacementByIds($placementIds);

        if ($placements->count() !== count($placementIds)) {
            $foundIds = $placements->pluck('id')->all();
            $missingIds = array_diff($placementIds, $foundIds);

            throw new InventoryPlacementNotFoundException('Некоторые размещения не найдены '.implode(', ', $missingIds));
        }

        $inventoryIds = $placements->pluck('inventory_id')->sort()->values()->all();

        return DB::transaction(function () use ($input, $placementIds, $inventoryIds) {
            $this->inventoryRepository->getInventoryByIdsForUpdate($inventoryIds);

            $placements = $this->inventoryRepository->getPlacementByIdsForUpdate($placementIds);

            if ($placements->count() !== count($placementIds)) {
                $foundIds = $placements->pluck('id')->all();
                $missingIds = array_diff($placementIds, $foundIds);

                throw new InventoryPlacementNotFoundException('Некоторые размещения не найдены '.implode(', ', $missingIds));
            }

            $event = $this->eventRepository->getEventByIdForUpdate($input->eventId);

            if ($event === null) {
                throw new EventNotFoundException;
            }

            $reservationData = [];

            foreach ($input->reservations as $reservation) {
                $placementId = $reservation['inventory_placement_id'];
                $requestedQuantity = $reservation['quantity'];
                $placement = $placements->where('id', $placementId)->first();
                $inventoryId = $placement->inventory_id;
                $cellId = $placement->cell_id;
                $startsAt = Carbon::parse($event->starts_at);
                $endsAt = Carbon::parse($event->ends_at);

                $existReservation = $event->eventInventoryReservations->contains(
                    fn ($reservation) => $reservation->inventory_id === $inventoryId && $reservation->cell_id === $cellId
                );

                if ($existReservation) {
                    throw new ReservationAlreadyExistsException('Предмет '.$inventoryId.' из ячейки '.$cellId.' уже зарезервирован для данного события.');
                }

                if ($placement->quantity < $requestedQuantity) {
                    throw new InsufficientStockException('Недостаточно инвентаря в ячейке '.$cellId.' для размещении '.$placement->id);
                }

                $overlappingEvents = $this->eventRepository->getOverlappingByInterval($startsAt, $endsAt, $inventoryId, $cellId);

                $minimumAvailableQuantity = ($this->inventoryReservationAvailabilityCalculator)($overlappingEvents, $placement->quantity);

                if (($minimumAvailableQuantity - $requestedQuantity) < 0) {
                    throw new InventoryReservationConflictException(sprintf(
                        'Невозможно забронировать инвентарь. В период с %s по %s на складе не будет хватать свободных позиций.',
                        $startsAt->toDateTimeString(),
                        $endsAt->toDateTimeString()
                    ));
                }

                $reservationData[] = [
                    'inventory_id' => $inventoryId,
                    'cell_id' => $cellId,
                    'quantity' => $requestedQuantity,
                    'event_id' => $event->id,
                ];

            }

            $reservations = $this->eventRepository->addReservation($reservationData);
            $reservationOutput = collect($reservations)->map(function ($reservation) {
                return [
                    'id' => $reservation->id,
                    'inventory_id' => $reservation->inventory_id,
                    'cell_id' => $reservation->cell_id,
                    'quantity' => $reservation->quantity,
                    'event_id' => $reservation->event_id,
                ];
            })->all();

            return new AddReservationOutput($reservationOutput);
        });
    }
}
