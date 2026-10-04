<?php

namespace App\UseCases\Rack\Create;

readonly class CreateRacksInput
{
    /**
     * @param  list<CreateRackItemInput>  $items
     */
    public function __construct(
        public int $warehouseId,
        public array $items
    ) {}
}
