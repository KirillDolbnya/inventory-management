<?php

namespace App\UseCases\Inventory\Update;

use App\Exceptions\InventoryNameAlreadyTakenException;
use App\Exceptions\InventoryNotFoundException;
use App\Repositories\InventoryRepository;

class UpdateInventory
{
    public function __construct(
        private readonly InventoryRepository $inventoryRepository,
    ) {}

    /**
     * @throws InventoryNotFoundException
     * @throws InventoryNameAlreadyTakenException
     */
    public function __invoke(UpdateInventoryInput $input): UpdateInventoryOutput
    {
        $inventory = $this->inventoryRepository->getById($input->id);

        if ($inventory === null) {
            throw new InventoryNotFoundException;
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
