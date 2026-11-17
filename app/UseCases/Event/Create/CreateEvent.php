<?php

namespace App\UseCases\Event\Create;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\InventoryPlacementNotFoundException;
use App\Exceptions\InventoryReservationConflictException;
use App\Models\EventInventoryReservation;
use App\Repositories\EventRepository;
use App\Repositories\InventoryRepository;
use App\Services\Inventory\InventoryReservationAvailabilityCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CreateEvent
{
    public function __construct(
        private readonly InventoryRepository $inventoryRepository,
        private readonly EventRepository $eventRepository,
        private readonly InventoryReservationAvailabilityCalculator $inventoryReservationAvailabilityCalculator,
    ) {}

    /**
     * @throws InventoryPlacementNotFoundException
     * @throws InsufficientStockException
     * @throws InventoryReservationConflictException
     * @throws \LogicException
     * @throws \Throwable
     */
    public function __invoke(CreateEventInput $input): CreateEventOutput
    {
        $placementIds = collect($input->reservations)->pluck('inventoryPlacementId')->sort()->values()->all();

        $placements = $this->inventoryRepository->getPlacementByIds($placementIds);

        if ($placements->count() !== count($placementIds)) {
            $foundIds = $placements->pluck('id')->all();
            $missingIds = array_diff($placementIds, $foundIds);

            throw new InventoryPlacementNotFoundException('Некоторые размещения не найдены '.implode(', ', $missingIds));
        }

        $inventoryIds = $placements->pluck('inventory_id')->unique()->sort()->values()->all();

        return DB::transaction(function () use ($input, $inventoryIds, $placementIds) {

            $this->inventoryRepository->getInventoryByIdsForUpdate($inventoryIds);

            $placements = $this->inventoryRepository->getPlacementByIdsForUpdate($placementIds);

            if ($placements->count() !== count($placementIds)) {
                $foundIds = $placements->pluck('id')->all();
                $missingIds = array_diff($placementIds, $foundIds);

                throw new InventoryPlacementNotFoundException('Некоторые размещения не найдены '.implode(', ', $missingIds));
            }

            $event = $this->eventRepository->create($input->name, $input->description, $input->startsAt, $input->endsAt);

            $reservationData = [];

            foreach ($input->reservations as $reservation) {
                $placementId = $reservation->inventoryPlacementId;
                $requestedQuantity = $reservation->quantity;
                $placement = $placements->where('id', $placementId)->first();
                $inventoryId = $placement->inventory_id;
                $cellId = $placement->cell_id;
                $startsAt = Carbon::parse($event->starts_at);
                $endsAt = Carbon::parse($event->ends_at);

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

            $reservationOutput = collect($reservations)->map(function (EventInventoryReservation $reservation) {
                return new CreateEventReservationOutput(
                    $reservation->id,
                    $reservation->inventory_id,
                    $reservation->cell_id,
                    $reservation->quantity
                );
            })->all();

            return new CreateEventOutput($event->id, $event->name, $event->description, $event->starts_at, $event->ends_at, $reservationOutput);
        });
    }
}
