<?php

namespace App\UseCases\Event\QueryList;

use App\Models\Event;
use App\Repositories\EventRepository;
use Carbon\Carbon;

class QueryListEvent
{
    public function __construct(
        private readonly EventRepository $eventRepository,
    ) {}

    public function __invoke(): QueryListEventItemsOutput
    {
        $events = $this->eventRepository->getAllEvent();

        $eventOutputs = $events->map(function (Event $event) {
            $startsAt = Carbon::parse($event->starts_at);
            $endsAt = Carbon::parse($event->ends_at);

            return new QueryListEventItemOutput(
                $event->id,
                $event->name,
                $event->description,
                $startsAt,
                $endsAt
            );
        })->values()->all();

        return new QueryListEventItemsOutput($eventOutputs);
    }
}
