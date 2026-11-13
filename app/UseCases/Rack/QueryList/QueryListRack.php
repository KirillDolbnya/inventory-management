<?php

namespace App\UseCases\Rack\QueryList;

use App\Exceptions\WarehouseNotFoundException;
use App\Models\Rack;
use App\Repositories\RackRepository;
use App\Repositories\WarehouseRepository;

class QueryListRack
{
    public function __construct(
        private readonly WarehouseRepository $warehouseRepository,
        private readonly RackRepository $rackRepository,
    ) {}

    /**
     * @throws WarehouseNotFoundException
     */
    public function __invoke(QueryListRackInput $input): QueryListRackItemsOutput
    {
        $warehouse = $this->warehouseRepository->getById($input->warehouseId);

        if ($warehouse === null) {
            throw new WarehouseNotFoundException;
        }

        $racks = $this->rackRepository->getAllByWarehouseId($warehouse->id);

        $items = $racks->map(fn (Rack $rack) => new QueryListRackItemOutput(
            $rack->id,
            $rack->code,
            $rack->levels_count,
            $rack->cells_per_level,
            $rack->cells->all(),
        ))->all();

        return new QueryListRackItemsOutput($items);
    }
}
