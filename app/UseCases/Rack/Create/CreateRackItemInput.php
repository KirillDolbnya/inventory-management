<?php

namespace App\UseCases\Rack\Create;

readonly class CreateRackItemInput
{
    public function __construct(
        public string $code,
        public int $levelsCount,
        public int $cellsPerLevel,
    ) {}
}
