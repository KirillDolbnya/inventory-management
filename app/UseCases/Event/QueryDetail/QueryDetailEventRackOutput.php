<?php

namespace App\UseCases\Event\QueryDetail;

readonly class QueryDetailEventRackOutput
{
    public function __construct(
        public int $rackId,
        public string $code
    ) {}
}
