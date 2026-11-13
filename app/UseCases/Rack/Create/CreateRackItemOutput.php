<?php

namespace App\UseCases\Rack\Create;

use App\Models\Cell;

readonly class CreateRackItemOutput
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
