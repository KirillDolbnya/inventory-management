<?php

namespace App\Repositories;

use App\Models\Cell;

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
}
