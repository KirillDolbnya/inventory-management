<?php

namespace App\UseCases\Event\QueryDetail;

readonly class QueryDetailEventCellOutput
{
    public function __construct(
        public int $cellId,
        public int $number,
        public int $level,
        public int $position,
    ) {}
}
