<?php

namespace App\UseCases\Inventory\Delete;

use App\Exceptions\InventoryHasDependenciesException;
use App\Exceptions\InventoryNotFoundException;
use App\Repositories\EventRepository;
use App\Repositories\InventoryRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DeleteInventory
{
    public function __construct(
        private readonly InventoryRepository $inventoryRepository,
        private readonly EventRepository $eventRepository,
    ) {}

    /**
     * @throws InventoryNotFoundException
     * @throws \Throwable
     */
    public function __invoke(DeleteInventoryInput $input): void
    {
        $inventory = $this->inventoryRepository->getById($input->id);

        if ($inventory === null) {
            throw new InventoryNotFoundException;
        }

        DB::transaction(function () use ($inventory) {
            $inventory = $this->inventoryRepository->getByIdForUpdate($inventory->id);

            if ($inventory === null) {
                throw new InventoryNotFoundException;
            }

            $now = Carbon::now();
            $placements = $this->inventoryRepository->getPlacementByInventoryIdForUpdate($inventory->id);
            $reservations = $this->eventRepository->getReservationsByInventoryIdForUpdate($inventory->id, $now);

            if ($placements->isNotEmpty() || $reservations->isNotEmpty()) {
                throw new InventoryHasDependenciesException;
            }

            $this->inventoryRepository->delete($inventory);
        });
    }
}
