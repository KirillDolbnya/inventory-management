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
        return Cell::query()->where('rack_id', $rackId)->orderBy('number', 'asc')->get();
    }

    /**
     * @return Collection<int, Cell>
     */
    public function getByRackIdWithTrashed(int $rackId): Collection
    {
        return Cell::query()->where('rack_id', $rackId)->orderBy('number', 'asc')->withTrashed()->get();
    }

    /**
     * @param  list<int>  $cellIds
     */
    public function deleteCellByIds(array $cellIds): void
    {
        Cell::query()->whereIn('id', $cellIds)->delete();
    }

    /**
     * @param  list<int>  $cellIds
     */
    public function deleteCells(array $cellIds): void
    {
        Cell::query()->whereIn('id', $cellIds)->delete();
    }

    /**
     * @param  list<array{
     *     id: int,
     *     rack_id: int,
     *     number: int,
     *     level: int,
     *     position: int,
     *     deleted_at: null
     * }>  $cells
     */
    public function updateCells(array $cells): void
    {
        Cell::query()->upsert($cells, ['id'], ['number', 'level', 'position', 'deleted_at']);
    }

    /**
     * @param  list<array{
     *     rack_id: int,
     *     number: int,
     *     level: int,
     *     position: int
     * }>  $cells
     */
    public function createCells(array $cells): void
    {
        Cell::query()->insert($cells);
    }
}
