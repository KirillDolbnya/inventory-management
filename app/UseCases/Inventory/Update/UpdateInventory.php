<?php

namespace App\UseCases\Inventory\Update;

use App\Exceptions\InventoryNameAlreadyTakenException;
use App\Exceptions\InventoryNotFoundException;
use App\Exceptions\InventoryQuantityLessThanPlacedException;
use App\Models\Inventory;
use App\Repositories\InventoryRepository;
use Illuminate\Support\Facades\DB;

class UpdateInventory
{
    public function __construct(
        private readonly InventoryRepository $inventoryRepository,
    ) {}

    /**
     * @throws InventoryNotFoundException
     * @throws InventoryNameAlreadyTakenException
     * @throws \Throwable
     */
    public function __invoke(UpdateInventoryInput $input): UpdateInventoryOutput
    {
        $inventory = $this->inventoryRepository->getById($input->id);

        if ($inventory === null) {
            throw new InventoryNotFoundException;
        }

        if ($input->quantity !== null) {
            return $this->updateWithLock($inventory, $input);
        }

        $nameChange = $this->prepareNameChange($inventory->id, $inventory->name, $input->name);

        $changes = [];

        if ($nameChange !== null) {
            $changes['name'] = $nameChange;
        }

        if ($input->descriptionProvided && $input->description !== $inventory->description) {
            $changes['description'] = $input->description;
        }

        if ($changes !== []) {
            $inventory = $this->inventoryRepository->update($inventory, $changes);
        }

        return new UpdateInventoryOutput($inventory->id, $inventory->name, $inventory->description, $inventory->quantity);
    }

    /**
     * @throws \Throwable
     */
    private function updateWithLock(Inventory $inventory, UpdateInventoryInput $input): UpdateInventoryOutput
    {
        return DB::transaction(function () use ($inventory, $input) {
            $inventory = $this->inventoryRepository->getByIdForUpdate($inventory->id);

            if ($inventory === null) {
                throw new InventoryNotFoundException;
            }

            $placements = $this->inventoryRepository->getPlacementByInventoryIdForUpdate($inventory->id);

            $changes = [];
            $placementsQuantity = $placements->isNotEmpty() ? $placements->sum('quantity') : 0;

            if ($input->quantity < $placementsQuantity) {
                throw new InventoryQuantityLessThanPlacedException;
            }

            $changes['quantity'] = $input->quantity;

            $nameChange = $this->prepareNameChange($inventory->id, $inventory->name, $input->name);

            if ($nameChange !== null) {
                $changes['name'] = $nameChange;
            }

            if ($input->descriptionProvided && $input->description !== $inventory->description) {
                $changes['description'] = $input->description;
            }

            $inventory = $this->inventoryRepository->update($inventory, $changes);

            return new UpdateInventoryOutput($inventory->id, $inventory->name, $inventory->description, $inventory->quantity);
        });
    }

    /**
     * @throws InventoryNameAlreadyTakenException
     */
    private function prepareNameChange(int $id, string $currentName, ?string $newName): ?string
    {
        if ($newName !== $currentName && $newName !== null) {
            if ($this->inventoryRepository->existsByName($newName, $id)) {
                throw new InventoryNameAlreadyTakenException;
            }

            return $newName;
        }

        return null;
    }
}
