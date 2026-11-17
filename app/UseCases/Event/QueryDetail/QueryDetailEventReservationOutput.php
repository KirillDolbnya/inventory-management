<?php

namespace App\UseCases\Event\QueryDetail;

readonly class QueryDetailEventReservationOutput
{
    public function __construct(
        public int $reservationId,
        public int $reservationQuantity,
        public QueryDetailEventInventoryOutput $inventory,
        public QueryDetailEventWarehouseOutput $warehouse,
        public QueryDetailEventRackOutput $rack,
        public QueryDetailEventCellOutput $cell
    ) {}
}
