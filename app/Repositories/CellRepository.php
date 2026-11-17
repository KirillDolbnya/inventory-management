<?php

namespace App\Repositories;

use App\Models\Cell;
use Illuminate\Support\Collection;

class CellRepository
{
    public function getById(int $id): ?Cell
    {
        return Cell::query()->find($id);
    }

    public function cellExistsById(int $cellId): bool
    {
        return Cell::query()->where(['id' => $cellId])->exists();
    }

    /**
     * @return Collection<int, Cell>
     */
    public function getByRackId(int $rackId): Collection
    {
        return Cell::query()->where('rack_id', $rackId)->get();
    }

    /**
     * @param  list<int>  $cellIds
     */
    public function deleteCellByIds(array $cellIds): void
    {
        Cell::query()->whereIn('id', $cellIds)->delete();
    }
}
