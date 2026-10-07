<?php

namespace App\UseCases\Rack\QueryList;

readonly class QueryListRackItemsOutput
{
    /**
     * @param  list<QueryListRackItemOutput>  $racks
     */
    public function __construct(
        public array $racks
    ) {}
}
