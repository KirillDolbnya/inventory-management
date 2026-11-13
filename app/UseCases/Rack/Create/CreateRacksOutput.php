<?php

namespace App\UseCases\Rack\Create;

readonly class CreateRacksOutput
{
    /**
     * @param  list<CreateRackItemOutput>  $racks
     */
    public function __construct(
        public array $racks
    ) {}
}
