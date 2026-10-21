<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\EventNotFoundException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InventoryPlacementNotFoundException;
use App\Exceptions\InventoryReservationConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\EventCreateRequest;
use App\UseCases\Event\Create\CreateEvent;
use App\UseCases\Event\Create\CreateEventInput;
use App\UseCases\Event\Create\CreateEventReservationInput;
use App\UseCases\Event\QueryDetail\QueryDetailEvent;
use App\UseCases\Event\QueryDetail\QueryDetailEventInput;
use App\UseCases\Event\QueryDetail\QueryDetailEventReservationOutput;
use App\UseCases\Event\QueryList\QueryListEvent;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    /**
     * @throws InventoryPlacementNotFoundException
     * @throws InsufficientStockException
     * @throws InventoryReservationConflictException
     * @throws \LogicException
     * @throws \Throwable
     */
    #[ScrambleResponse(
        status: 404,
        description: 'Resource not found',
        type: 'array{message: string}'
    )]
    #[ScrambleResponse(
        status: 409,
        description: 'Resource not found',
        type: 'array{message: string}'
    )]
    public function store(EventCreateRequest $request, CreateEvent $createEvent): JsonResponse
    {
        $startsAt = Carbon::parse($request->input('starts_at'))->utc();
        $endsAt = Carbon::parse($request->input('ends_at'))->utc();

        $reservationInputs = collect($request->array('reservations'))->map(function ($reservation) {
            return new CreateEventReservationInput($reservation['inventory_placement_id'], $reservation['quantity']);
        })->all();

        $input = new CreateEventInput(
            $request->string('name')->toString(),
            $request->filled('description') ? $request->string('description')->toString() : null,
            $startsAt,
            $endsAt,
            $reservationInputs
        );

        $output = ($createEvent)($input);

        return response()->json([
            'id' => $output->id,
            'name' => $output->name,
            'description' => $output->description,
            'starts_at' => $output->startsAt,
            'ends_at' => $output->endsAt,
            'reservations' => array_map(function ($reservation) {
                return [
                    'id' => $reservation->id,
                    'inventory_id' => $reservation->inventoryId,
                    'cell_id' => $reservation->cellId,
                    'quantity' => $reservation->quantity,
                ];
            }, $output->reservations),
        ], 201);
    }

    public function index(QueryListEvent $queryListEvent): JsonResponse
    {
        $output = ($queryListEvent)();

        $outputData = collect($output->events)->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'description' => $item->description,
                'starts_at' => $item->startsAt,
                'ends_at' => $item->endsAt,
            ];
        });

        return response()->json([
            'data' => $outputData,
        ]);
    }

    /**
     * @throws EventNotFoundException
     */
    #[ScrambleResponse(
        status: 404,
        description: 'Resource not found',
        type: 'array{message: string}'
    )]
    public function show(Request $request, QueryDetailEvent $queryDetailEvent): JsonResponse
    {
        $input = new QueryDetailEventInput((int) $request->route('eventId'));

        $output = ($queryDetailEvent)($input);

        $responseData = [
            'id' => $output->eventId,
            'name' => $output->name,
            'description' => $output->description,
            'starts_at' => $output->startsAt,
            'ends_at' => $output->endsAt,

            'reservations' => collect($output->reservations)->map(function (QueryDetailEventReservationOutput $reservation) {
                return [
                    'id' => $reservation->reservationId,
                    'quantity' => $reservation->reservationQuantity,

                    'inventory' => [
                        'id' => $reservation->inventory->inventoryId,
                        'name' => $reservation->inventory->name,
                        'quantity' => $reservation->inventory->quantity,
                    ],

                    'warehouse' => [
                        'id' => $reservation->warehouse->warehouseId,
                        'name' => $reservation->warehouse->name,
                    ],

                    'rack' => [
                        'id' => $reservation->rack->rackId,
                        'code' => $reservation->rack->code,
                    ],

                    'cell' => [
                        'id' => $reservation->cell->cellId,
                        'number' => $reservation->cell->number,
                        'level' => $reservation->cell->level,
                        'position' => $reservation->cell->position,
                    ],
                ];
            })->all(),
        ];

        return response()->json($responseData);
    }
}
