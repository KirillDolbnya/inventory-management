<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\EventNotFoundException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InventoryPlacementNotFoundException;
use App\Exceptions\InventoryReservationConflictException;
use App\Exceptions\ReservationNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddReservationRequest;
use App\Http\Requests\EventCreateRequest;
use App\Http\Requests\EventUpdateRequest;
use App\Http\Requests\UpdateReservationRequest;
use App\UseCases\Event\AddReservation\AddReservation;
use App\UseCases\Event\AddReservation\AddReservationInput;
use App\UseCases\Event\Create\CreateEvent;
use App\UseCases\Event\Create\CreateEventInput;
use App\UseCases\Event\Create\CreateEventReservationInput;
use App\UseCases\Event\Delete\DeleteEvent;
use App\UseCases\Event\Delete\DeleteEventInput;
use App\UseCases\Event\DeleteReservation\DeleteReservation;
use App\UseCases\Event\DeleteReservation\DeleteReservationInput;
use App\UseCases\Event\QueryDetail\QueryDetailEvent;
use App\UseCases\Event\QueryDetail\QueryDetailEventInput;
use App\UseCases\Event\QueryDetail\QueryDetailEventReservationOutput;
use App\UseCases\Event\QueryList\QueryListEvent;
use App\UseCases\Event\Update\UpdateEvent;
use App\UseCases\Event\Update\UpdateEventInput;
use App\UseCases\Event\UpdateReservation\UpdateReservation;
use App\UseCases\Event\UpdateReservation\UpdateReservationInput;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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

    /**
     * @throws EventNotFoundException
     */
    #[ScrambleResponse(
        status: 404,
        description: 'Resource not found',
        type: 'array{message: string}'
    )]
    public function delete(Request $request, DeleteEvent $deleteEvent): Response
    {
        $input = new DeleteEventInput((int) $request->route('eventId'));

        ($deleteEvent)($input);

        return response()->noContent();
    }

    /**
     * @throws InventoryReservationConflictException
     * @throws EventNotFoundException
     * @throws \Throwable
     */
    #[ScrambleResponse(
        status: 422,
        description: 'Validation error',
        type: 'array{message: string, errors: array<string, string[]>}'
    )]
    #[ScrambleResponse(
        status: 404,
        description: 'Resource not found',
        type: 'array{message: string}'
    )]
    public function update(EventUpdateRequest $request, UpdateEvent $updateEvent): JsonResponse
    {
        $startsAt = $request->has('starts_at') ? Carbon::parse($request->input('starts_at'))->utc() : null;
        $endsAt = $request->has('ends_at') ? Carbon::parse($request->input('ends_at'))->utc() : null;

        $input = new UpdateEventInput((int) $request->route('eventId'), $request->has('name') ? $request->string('name')->toString() : null, $request->filled('description') ? $request->string('description')->toString() : null, $request->has('description'), $startsAt, $endsAt);

        $output = ($updateEvent)($input);

        return response()->json([
            'id' => $output->id,
            'name' => $output->name,
            'description' => $output->description,
            'starts_at' => $output->startsAt,
            'ends_at' => $output->endsAt,
        ]);
    }

    /**
     * @throws InventoryPlacementNotFoundException
     * @throws EventNotFoundException
     * @throws \Throwable
     */
    #[ScrambleResponse(
        status: 422,
        description: 'Validation error',
        type: 'array{message: string, errors: array<string, string[]>}'
    )]
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
    public function reservationsAdd(AddReservationRequest $request, AddReservation $addReservation): JsonResponse
    {
        $input = new AddReservationInput(
            (int) $request->route('eventId'),
            $request->input('reservations'),
        );

        $output = ($addReservation)($input);

        $outputData = collect($output->reservations)->map(function ($reservation) {
            return [
                'id' => $reservation['id'],
                'event_id' => $reservation['event_id'],
                'inventory_id' => $reservation['inventory_id'],
                'cell_id' => $reservation['cell_id'],
                'quantity' => $reservation['quantity'],
            ];
        });

        return response()->json([
            'data' => $outputData,
        ], 201);
    }

    /**
     * @throws EventNotFoundException
     * @throws \Throwable
     * @throws ReservationNotFoundException
     */
    #[ScrambleResponse(
        status: 422,
        description: 'Validation error',
        type: 'array{message: string, errors: array<string, string[]>}'
    )]
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
    public function reservationsUpdate(UpdateReservationRequest $request, UpdateReservation $updateReservation): JsonResponse
    {
        $input = new UpdateReservationInput((int) $request->route('eventId'), (int) $request->route('reservationId'), $request->input('quantity'));

        $output = ($updateReservation)($input);

        return response()->json([
            'id' => $output->id,
            'event_id' => $output->eventId,
            'inventory_id' => $output->inventoryId,
            'cell_id' => $output->cellId,
            'quantity' => $output->quantity,
        ]);
    }

    /**
     * @throws EventNotFoundException
     * @throws \Throwable
     * @throws ReservationNotFoundException
     */
    #[ScrambleResponse(
        status: 404,
        description: 'Resource not found',
        type: 'array{message: string}'
    )]
    public function reservationsDelete(Request $request, DeleteReservation $deleteReservation): Response
    {
        $input = new DeleteReservationInput((int) $request->route('eventId'), (int) $request->route('reservationId'));

        ($deleteReservation)($input);

        return response()->noContent();
    }
}
