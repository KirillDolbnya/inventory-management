<?php

namespace App\UseCases\Rack\Update;

readonly class UpdateRackOutput
{
    /**
     * @param  list<array{
     *     id: int,
     *     number: int,
     *     level: int,
     *     position: int
     * }>  $cells
     */
    public function __construct(
        public int $rackId,
        public string $rackCode,
        public int $levelsCount,
        public int $cellsPerLevel,
        public array $cells
    ) {}
}
