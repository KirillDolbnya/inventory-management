<?php

namespace App\UseCases\Warehouse\Create;

use App\Contracts\Addresses\AddressResolver;
use App\Exceptions\FiasIdAlreadyTakenException;
use App\Exceptions\FiasIdNotFoundException;
use App\Exceptions\WarehouseNameAlreadyTakenException;
use App\Repositories\WarehouseRepository;

class CreateWarehouse
{
    public function __construct(
        private readonly WarehouseRepository $warehouseRepository,
        private readonly AddressResolver $addressResolver
    ) {}

    /**
     * @throws WarehouseNameAlreadyTakenException
     * @throws FiasIdAlreadyTakenException
     * @throws FiasIdNotFoundException
     */
    public function __invoke(CreateWarehouseInput $input): CreateWarehouseOutput
    {
        if ($this->warehouseRepository->existsByName($input->name)) {
            throw new WarehouseNameAlreadyTakenException;
        }

        if ($this->warehouseRepository->existsByFiasId($input->fiasId)) {
            throw new FiasIdAlreadyTakenException;
        }

        $resolvedAddress = $this->addressResolver->getAddressByFiasId($input->fiasId);

        $warehouse = $this->warehouseRepository->create($input->name, $resolvedAddress->getAddress(), $resolvedAddress->getFiasId(), $resolvedAddress->getLatitude(), $resolvedAddress->getLongitude());

        return new CreateWarehouseOutput($warehouse->id, $warehouse->name, $warehouse->address, $warehouse->latitude, $warehouse->longitude);
    }
}
