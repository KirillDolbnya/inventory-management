<?php

namespace App\Services\Inventory;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Support\Collection;

readonly class InventoryReservationAvailabilityCalculator
{
    /**
     * @param  Collection<int, Event>  $events
     *
     * @throws \LogicException
     */
    public function __invoke(Collection $events, int $physicalQuantity): int
    {
        $timeline = [];
        foreach ($events as $event) {
            $reservation = $event->eventInventoryReservations->first();
            if ($reservation === null) {
                throw new \LogicException(
                    'Событие не содержит ожидаемого резервирования инвентаря.'
                );
            }

            $timeline[] = [
                'time' => Carbon::parse($event->starts_at)->timestamp,
                'amount' => -$reservation->quantity,
            ];

            $timeline[] = [
                'time' => Carbon::parse($event->ends_at)->timestamp,
                'amount' => $reservation->quantity,
            ];
        }

        usort($timeline, function (array $a, array $b) {
            if ($a['time'] === $b['time']) {
                return $b['amount'] <=> $a['amount'];
            }

            return $a['time'] <=> $b['time'];
        });

        $currentAvailableStock = $physicalQuantity;
        $minimumAvailableQuantity = $physicalQuantity;

        foreach ($timeline as $point) {
            $currentAvailableStock += $point['amount'];

            if ($currentAvailableStock < $minimumAvailableQuantity) {
                $minimumAvailableQuantity = $currentAvailableStock;
            }
        }

        return $minimumAvailableQuantity;
    }
}
