<?php

namespace App\UseCases\Event\QueryList;

readonly class QueryListEventItemsOutput
{
    /**
     * @param  list<QueryListEventItemOutput>  $events
     */
    public function __construct(
        public array $events
    ) {}
}
