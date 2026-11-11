<?php

namespace App\Repositories;

use App\Models\Event;
use App\Models\EventInventoryReservation;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EventRepository
{
    public function create(string $name, ?string $description, Carbon $startsAt, Carbon $endsAt): Event
    {
        return Event::query()->create([
            'name' => $name,
            'description' => $description,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }

    /**
     * @param  list<array{inventory_id: int, cell_id: int, quantity: int, event_id: int}>  $reservationsData
     * @return array<int, EventInventoryReservation>
     */
    public function addReservation(array $reservationsData): array
    {
        $reservations = [];

        foreach ($reservationsData as $reservationData) {
            $reservation = EventInventoryReservation::query()->create([
                'event_id' => $reservationData['event_id'],
                'inventory_id' => $reservationData['inventory_id'],
                'quantity' => $reservationData['quantity'],
                'cell_id' => $reservationData['cell_id'],
            ]);

            $reservations[] = $reservation;
        }

        return $reservations;
    }

    /**
     * @return Collection<int, Event>
     */
    public function getOverlappingByInterval(Carbon $startsAt, Carbon $endsAt, int $inventoryId, int $cellId, ?int $eventId = null): Collection
    {
        $query = Event::query()->whereHas('eventInventoryReservations', function ($query) use ($inventoryId, $cellId) {
            $query->where('inventory_id', $inventoryId)->where('cell_id', $cellId);
        })->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt)->with('eventInventoryReservations', function ($query) use ($inventoryId, $cellId) {
            $query->where('inventory_id', $inventoryId)
                ->where('cell_id', $cellId);
        });

        if ($eventId !== null) {
            $query->whereKeyNot($eventId);
        }

        return $query->get();
    }

    /**
     * @return Collection<int, Event>
     */
    public function getActiveAndFutureReservations(Carbon $now, int $inventoryId, int $cellId): Collection
    {
        return Event::query()->whereHas('eventInventoryReservations', function ($query) use ($inventoryId, $cellId) {
            $query->where('inventory_id', $inventoryId)->where('cell_id', $cellId);
        })->where('ends_at', '>', $now)->with('eventInventoryReservations', function ($query) use ($inventoryId, $cellId) {
            $query->where('inventory_id', $inventoryId)->where('cell_id', $cellId);
        })->orderBy('starts_at', 'asc')->get();
    }

    /**
     * @return Collection<int, Event>
     */
    public function getAllEvent(): Collection
    {
        return Event::query()->orderBy('starts_at', 'asc')->get();
    }

    public function getByIdWithReservations(int $eventId): ?Event
    {
        return Event::query()->with([
            'eventInventoryReservations' => function ($query) {
                $query->orderBy('id', 'asc');
            },

            'eventInventoryReservations.inventory',
            'eventInventoryReservations.cell',
            'eventInventoryReservations.cell.rack',
            'eventInventoryReservations.cell.rack.warehouse',
        ])->find($eventId);
    }

    public function getEventById(int $id): ?Event
    {
        return Event::query()->with('eventInventoryReservations')->find($id);
    }

    public function deleteWithReservations(Event $event): void
    {
        $event->delete();
    }

    public function getEventByIdForUpdate(int $id): ?Event
    {
        return Event::query()->with('eventInventoryReservations')->lockForUpdate()->find($id);
    }

    /**
     * @param array{
     * name?: string,
     * description?: string|null,
     * starts_at?: Carbon,
     * ends_at?: Carbon
     * } $data
     */
    public function update(Event $event, array $data): Event
    {
        $event->update($data);

        return $event;
    }

    public function updateReservation(EventInventoryReservation $eventInventoryReservation, int $quantity): EventInventoryReservation
    {
        $eventInventoryReservation->update(['quantity' => $quantity]);

        return $eventInventoryReservation;
    }

    public function deleteReservation(EventInventoryReservation $eventInventoryReservation): void
    {
        $eventInventoryReservation->delete();
    }
}
