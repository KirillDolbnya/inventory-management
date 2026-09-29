<?php

namespace App\UseCases\Warehouse\Update;

use App\Contracts\Addresses\AddressResolver;
use App\Data\Addresses\ResolvedAddress;
use App\Exceptions\FiasIdAlreadyTakenException;
use App\Exceptions\FiasIdNotFoundException;
use App\Exceptions\WarehouseNameAlreadyTakenException;
use App\Exceptions\WarehouseNotFoundException;
use App\Repositories\WarehouseRepository;

class UpdateWarehouse
{
    public function __construct(
        private readonly WarehouseRepository $warehouseRepository,
        private readonly AddressResolver $addressResolver
    ) {}

    /**
     * @throws FiasIdNotFoundException
     * @throws WarehouseNameAlreadyTakenException
     * @throws FiasIdAlreadyTakenException
     * @throws WarehouseNotFoundException
     */
    public function __invoke(UpdateWarehouseInput $input): UpdateWarehouseOutput
    {
        $warehouse = $this->warehouseRepository->getById($input->id);

        if ($warehouse === null) {
            throw new WarehouseNotFoundException;
        }

        $nameChange = $this->prepareNameChange($warehouse->id, $warehouse->name, $input->name);
        $addressChange = $this->prepareAddressChange($warehouse->id, $warehouse->fias_id, $input->fiasId);

        $warehouse = $this->warehouseRepository->update($warehouse, $nameChange, $addressChange);

        return new UpdateWarehouseOutput($warehouse->id, $warehouse->name, $warehouse->address, $warehouse->latitude, $warehouse->longitude);
    }

    /**
     * @throws WarehouseNameAlreadyTakenException
     */
    private function prepareNameChange(int $id, string $currentName, ?string $newName): ?string
    {
        if ($currentName !== $newName && $newName !== null) {
            if ($this->warehouseRepository->existsByName($newName, $id)) {
                throw new WarehouseNameAlreadyTakenException;
            }

            return $newName;
        }

        return null;
    }

    /**
     * @throws FiasIdAlreadyTakenException
     * @throws FiasIdNotFoundException
     */
    private function prepareAddressChange(int $id, string $currentFiasId, ?string $newFiasId): ?ResolvedAddress
    {
        if ($currentFiasId !== $newFiasId && $newFiasId !== null) {
            if ($this->warehouseRepository->existsByFiasId($newFiasId, $id)) {
                throw new FiasIdAlreadyTakenException;
            }

            return $this->addressResolver->getAddressByFiasId($newFiasId);
        }

        return null;
    }
}
