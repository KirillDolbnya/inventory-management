<?php

namespace App\UseCases\Rack\QueryList;

use App\Models\Cell;

readonly class QueryListRackItemOutput
{
    /**
     * @param  array<int, Cell>  $cells
     */
    public function __construct(
        public int $id,
        public string $code,
        public int $levelsCount,
        public int $cellsPerLevel,
        public array $cells
    ) {}
}
