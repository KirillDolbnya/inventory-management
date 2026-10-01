<?php

namespace App\UseCases\Warehouse\QueryDetail;

use App\Exceptions\WarehouseNotFoundException;
use App\Repositories\WarehouseRepository;

class QueryDetailWarehouse
{
    public function __construct(
        private readonly WarehouseRepository $warehouseRepository
    ) {}

    /**
     * @throws WarehouseNotFoundException
     */
    public function __invoke(QueryDetailWarehouseInput $input): QueryDetailWarehouseOutput
    {
        $warehouse = $this->warehouseRepository->getById($input->id);

        if ($warehouse === null) {
            throw new WarehouseNotFoundException;
        }

        return new QueryDetailWarehouseOutput($warehouse->id, $warehouse->name, $warehouse->address, $warehouse->latitude, $warehouse->longitude);
    }
}
