<?php

namespace App\Repositories;

use App\Models\Cell;

class CellRepository
{
    public function cellExistsById(int $cellId): bool
    {
        return Cell::query()->where(['id' => $cellId])->exists();
    }
}
