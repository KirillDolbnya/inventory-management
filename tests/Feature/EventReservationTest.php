<?php

use App\Models\Cell;
use App\Models\Event;
use App\Models\EventInventoryReservation;
use App\Models\Inventory;
use App\Models\InventoryPlacement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

describe('Event reservations', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();

        $this->inventoryQuantity = 10;

        $this->inventory = Inventory::factory()->create([
            'name' => 'Гвозди',
            'description' => 'Описание',
            'quantity' => $this->inventoryQuantity,
        ]);

        $this->placementQuantity = 5;

        $this->placement = InventoryPlacement::factory()->create([
            'inventory_id' => $this->inventory->id,
            'quantity' => $this->placementQuantity,
        ]);

        $this->travelTo(Carbon::parse('2026-11-05T10:00:00+03:00'));

        $this->startsAt = Carbon::parse('2026-11-05T10:00:00+03:00');
        $this->endsAt = $this->startsAt->clone()->addHours(1);
        $this->name = 'Событие';
        $this->description = 'Описание';

        $this->payload = [
            'name' => $this->name,
            'description' => $this->description,
            'starts_at' => $this->startsAt->toIso8601String(),
            'ends_at' => $this->endsAt->toIso8601String(),
        ];
    });

    it('creates an event with inventory reservations', function () {
        $otherInventoryPlacement = InventoryPlacement::factory()->create([
            'inventory_id' => $this->inventory->id,
            'quantity' => $this->placementQuantity,
        ]);

        $this->payload['reservations'] = [
            [
                'inventory_placement_id' => $this->placement->id,
                'quantity' => 3,
            ],
            [
                'inventory_placement_id' => $otherInventoryPlacement->id,
                'quantity' => 2,
            ],
        ];

        $response = $this->be($this->user)->postJson(route('events-create'), $this->payload);

        $response->assertStatus(201);

        $response->assertJson([
            'name' => $this->name,
            'description' => $this->description,
            'starts_at' => $this->startsAt->toISOString(),
            'ends_at' => $this->endsAt->toISOString(),
            'reservations' => [
                [
                    'inventory_id' => $this->inventory->id,
                    'cell_id' => $this->placement->cell_id,
                    'quantity' => 3,
                ],
                [
                    'inventory_id' => $this->inventory->id,
                    'cell_id' => $otherInventoryPlacement->cell_id,
                    'quantity' => 2,
                ],
            ],
        ]);

        $eventId = $response->json('id');

        assertDatabaseHas('events', [
            'id' => $eventId,
            'name' => $this->name,
            'description' => $this->description,
            'starts_at' => $this->startsAt->clone()->setTimezone('UTC')->format('Y-m-d H:i:s'),
            'ends_at' => $this->endsAt->clone()->setTimezone('UTC')->format('Y-m-d H:i:s'),
        ]);

        assertDatabaseCount('event_inventory_reservations', 2);

        assertDatabaseHas('event_inventory_reservations', [
            'event_id' => $eventId,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->placement->cell_id,
            'quantity' => 3,
        ]);

        assertDatabaseHas('event_inventory_reservations', [
            'event_id' => $eventId,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $otherInventoryPlacement->cell_id,
            'quantity' => 2,
        ]);

        assertDatabaseHas('inventories', [
            'id' => $this->inventory->id,
            'quantity' => $this->inventoryQuantity,
        ]);

        assertDatabaseHas('inventory_placements', [
            'id' => $this->placement->id,
            'inventory_id' => $this->inventory->id,
            'quantity' => $this->placementQuantity,
        ]);

        assertDatabaseHas('inventory_placements', [
            'id' => $otherInventoryPlacement->id,
            'inventory_id' => $this->inventory->id,
            'quantity' => $this->placementQuantity,
        ]);
    });

    it('does not count reservations for the same inventory in another cell', function () {
        $event = Event::factory()->create([
            'starts_at' => $this->startsAt->setTimezone('UTC')->format('Y-m-d H:i:s'),
            'ends_at' => $this->endsAt->setTimezone('UTC')->format('Y-m-d H:i:s'),
        ]);

        $reservation = EventInventoryReservation::factory()->create([
            'event_id' => $event->id,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->placement->cell_id,
            'quantity' => 3,
        ]);

        $otherInventoryPlacement = InventoryPlacement::factory()->create([
            'inventory_id' => $this->inventory->id,
            'quantity' => $this->placementQuantity,
        ]);

        $this->payload['reservations'] = [
            [
                'inventory_placement_id' => $otherInventoryPlacement->id,
                'quantity' => 3,
            ],
        ];

        $response = $this->be($this->user)->postJson(route('events-create'), $this->payload);

        $response->assertStatus(201);

        $responseReservationId = collect($response->json('reservations'))->value('id');

        expect($responseReservationId)->not()->toEqual($reservation->id);

        assertDatabaseHas('event_inventory_reservations', [
            'id' => $responseReservationId,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $otherInventoryPlacement->cell_id,
            'quantity' => 3,
        ]);
    });

    it('does not count reservations for another inventory in the same cell', function () {
        $inventory = Inventory::factory()->create([
            'quantity' => 5,
        ]);

        $placement = InventoryPlacement::factory()->create([
            'inventory_id' => $inventory->id,
            'cell_id' => $this->placement->cell_id,
            'quantity' => 5,
        ]);

        $event = Event::factory()->create([
            'starts_at' => $this->startsAt->clone()->setTimezone('UTC')->format('Y-m-d H:i:s'),
            'ends_at' => $this->endsAt->clone()->setTimezone('UTC')->format('Y-m-d H:i:s'),
        ]);

        EventInventoryReservation::factory()->create([
            'event_id' => $event->id,
            'inventory_id' => $inventory->id,
            'cell_id' => $placement->cell_id,
            'quantity' => 5,
        ]);

        $this->payload['reservations'] = [
            [
                'inventory_placement_id' => $this->placement->id,
                'quantity' => 3,
            ],
        ];

        $response = $this->be($this->user)->postJson(route('events-create'), $this->payload);

        $response->assertStatus(201);

        $reservationId = collect($response->json('reservations'))->value('id');

        assertDatabaseHas('event_inventory_reservations', [
            'id' => $reservationId,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->placement->cell_id,
            'quantity' => 3,
        ]);
    });

    it('rejects reservations that exceed availability during overlapping events', function () {
        $event = Event::factory()->create([
            'starts_at' => $this->startsAt->clone()->setTimezone('UTC')->format('Y-m-d H:i:s'),
            'ends_at' => $this->endsAt->clone()->addHours(1)->setTimezone('UTC')->format('Y-m-d H:i:s'),
        ]);

        EventInventoryReservation::factory()->create([
            'event_id' => $event->id,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->placement->cell_id,
            'quantity' => 3,
        ]);

        $anotherEvent = Event::factory()->create([
            'starts_at' => $this->startsAt->clone()->addHours(1)->setTimezone('UTC')->format('Y-m-d H:i:s'),
            'ends_at' => $this->endsAt->clone()->addHours(2)->setTimezone('UTC')->format('Y-m-d H:i:s'),
        ]);

        EventInventoryReservation::factory()->create([
            'event_id' => $anotherEvent->id,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->placement->cell_id,
            'quantity' => 2,
        ]);

        $this->payload['reservations'] = [
            [
                'inventory_placement_id' => $this->placement->id,
                'quantity' => 2,
            ],
        ];

        $this->payload = array_merge($this->payload, [
            'starts_at' => $this->startsAt->clone()->setTimezone('UTC')->toIso8601String(),
            'ends_at' => $this->endsAt->clone()->addHours(3)->setTimezone('UTC')->toIso8601String(),
        ]);

        $response = $this->be($this->user)->postJson(route('events-create'), $this->payload);

        $response->assertStatus(409);

        assertDatabaseCount('event_inventory_reservations', 2);
        assertDatabaseCount('events', 2);
    });

    it('rejects a quantity greater than the placement stock', function () {
        $this->payload['reservations'] = [
            [
                'inventory_placement_id' => $this->placement->id,
                'quantity' => 6,
            ],
        ];

        $response = $this->be($this->user)->postJson(route('events-create'), $this->payload);

        $response->assertStatus(409);

        assertDatabaseCount('event_inventory_reservations', 0);
        assertDatabaseCount('events', 0);
    });

    it('allows reserving the exact available quantity', function () {
        $event = Event::factory()->create([
            'starts_at' => $this->startsAt->clone()->setTimezone('UTC')->format('Y-m-d H:i:s'),
            'ends_at' => $this->endsAt->clone()->setTimezone('UTC')->format('Y-m-d H:i:s'),
        ]);

        EventInventoryReservation::factory()->create([
            'event_id' => $event->id,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->placement->cell_id,
            'quantity' => 2,
        ]);

        $this->payload['reservations'] = [
            [
                'inventory_placement_id' => $this->placement->id,
                'quantity' => 3,
            ],
        ];

        $response = $this->be($this->user)->postJson(route('events-create'), $this->payload);

        $response->assertStatus(201);

        assertDatabaseCount('event_inventory_reservations', 2);
        assertDatabaseCount('events', 2);
    });

    it('allows back-to-back reservations', function () {
        $event = Event::factory()->create([
            'starts_at' => $this->startsAt->clone()->setTimezone('UTC')->format('Y-m-d H:i:s'),
            'ends_at' => $this->endsAt->clone()->setTimezone('UTC')->format('Y-m-d H:i:s'),
        ]);

        EventInventoryReservation::factory()->create([
            'event_id' => $event->id,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->placement->cell_id,
            'quantity' => 3,
        ]);

        $anotherEvent = Event::factory()->create([
            'starts_at' => $this->startsAt->clone()->addHours(1)->setTimezone('UTC')->format('Y-m-d H:i:s'),
            'ends_at' => $this->endsAt->clone()->addHours(2)->setTimezone('UTC')->format('Y-m-d H:i:s'),
        ]);

        EventInventoryReservation::factory()->create([
            'event_id' => $anotherEvent->id,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->placement->cell_id,
            'quantity' => 3,
        ]);

        $this->payload = array_merge($this->payload, [
            'starts_at' => $this->startsAt->clone()->setTimezone('UTC')->toIso8601String(),
            'ends_at' => $this->endsAt->clone()->addHours(2)->setTimezone('UTC')->toIso8601String(),
        ]);

        $this->payload['reservations'] = [
            [
                'inventory_placement_id' => $this->placement->id,
                'quantity' => 2,
            ],
        ];

        $response = $this->be($this->user)->postJson(route('events-create'), $this->payload);

        $response->assertStatus(201);

        assertDatabaseCount('event_inventory_reservations', 3);
        assertDatabaseCount('events', 3);
    });

    it('rejects a reservation for a nonexistent placement', function () {
        $this->payload['reservations'] = [
            [
                'inventory_placement_id' => $this->placement->id + 1000,
                'quantity' => 3,
            ],
        ];

        $response = $this->be($this->user)->postJson(route('events-create'), $this->payload);

        $response->assertStatus(404);

        assertDatabaseCount('event_inventory_reservations', 0);
        assertDatabaseCount('events', 0);
    });

    it('rolls back the event when one reservation cannot be created', function () {
        $otherInventoryPlacement = InventoryPlacement::factory()->create([
            'inventory_id' => $this->inventory->id,
            'quantity' => $this->placementQuantity,
        ]);

        $this->payload['reservations'] = [
            [
                'inventory_placement_id' => $this->placement->id,
                'quantity' => 3,
            ],
            [
                'inventory_placement_id' => $otherInventoryPlacement->id,
                'quantity' => 10,
            ],
        ];

        $response = $this->be($this->user)->postJson(route('events-create'), $this->payload);

        $response->assertStatus(409);

        assertDatabaseCount('event_inventory_reservations', 0);
        assertDatabaseCount('events', 0);
    });

    it('rejects invalid event creation data', function (array $payload, string $errorKey, string $expectedMessage) {
        $response = $this->be($this->user)->postJson(route('events-create'), $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                $errorKey => $expectedMessage,
            ]);

        assertDatabaseCount('event_inventory_reservations', 0);
        assertDatabaseCount('events', 0);
    })->with([
        'name required (empty payload)' => [
            [],
            'name',
            'Название резерва обязательно для заполнения.',
        ],
        'name required (null value)' => [
            ['name' => null],
            'name',
            'Название резерва обязательно для заполнения.',
        ],
        'name must be string' => [
            ['name' => 12345],
            'name',
            'Название резерва должно быть строкой.',
        ],
        'name exceeds max length' => [
            ['name' => str_repeat('A', 256)],
            'name',
            'Название резерва не должно превышать 255 символов.',
        ],
        'description must be string' => [
            ['description' => 12345],
            'description',
            'Описание должно быть текстовой строкой.',
        ],
        'description exceeds max length' => [
            ['description' => str_repeat('B', 256)],
            'description',
            'Описание не должно превышать 255 символов.',
        ],
        'starts_at required' => [
            ['starts_at' => null],
            'starts_at',
            'Дата начала резервирования обязательна.',
        ],
        'starts_at incorrect ISO format' => [
            ['starts_at' => '2026-11-05 09:07:00'],
            'starts_at',
            'Указан неверный формат даты начала. Ожидается ISO 8601 (например, 2026-10-18T09:00:00+03:00).',
        ],
        'starts_at cannot be in past' => [
            ['starts_at' => '2020-01-01T09:00:00+03:00'],
            'starts_at',
            'Дата начала резервирования не может быть в прошлом.',
        ],
        'ends_at required' => [
            ['ends_at' => null],
            'ends_at',
            'Дата окончания резервирования обязательна.',
        ],
        'ends_at incorrect ISO format' => [
            ['ends_at' => '2026-11-05 09:09:00'],
            'ends_at',
            'Указан неверный формат даты окончания. Ожидается ISO 8601 (например, 2026-10-25T18:00:00+03:00).',
        ],
        'ends_at must be after starts_at' => [
            [
                'starts_at' => '2026-11-05T09:07:00+03:00',
                'ends_at' => '2026-11-05T09:05:00+03:00',
            ],
            'ends_at',
            'Дата окончания должна быть строго позже даты начала.',
        ],
        'reservations required' => [
            ['reservations' => null],
            'reservations',
            'Список резервируемых позиций обязателен.',
        ],
        'placement id required' => [
            ['reservations' => [['quantity' => 5]]],
            'reservations.0.inventory_placement_id',
            'ID размещения ячейки обязательно для заполнения.',
        ],
        'placement id must be integer' => [
            ['reservations' => [['inventory_placement_id' => 'not-an-integer', 'quantity' => 5]]],
            'reservations.0.inventory_placement_id',
            'ID размещения должно быть целым числом.',
        ],
        'placement id must be min 1' => [
            ['reservations' => [['inventory_placement_id' => 0, 'quantity' => 5]]],
            'reservations.0.inventory_placement_id',
            'Неверный ID размещения ячейки.',
        ],
        'placement ids must be distinct' => [
            [
                'reservations' => [
                    ['inventory_placement_id' => 1, 'quantity' => 2],
                    ['inventory_placement_id' => 1, 'quantity' => 3], // Дубликат
                ],
            ],
            'reservations.0.inventory_placement_id',
            'В списке позиций обнаружены дубликаты одинаковых ячеек.',
        ],
        'quantity required' => [
            ['reservations' => [['inventory_placement_id' => 1]]],
            'reservations.0.quantity',
            'Количество предмета обязательно для заполнения.',
        ],
        'quantity must be integer' => [
            ['reservations' => [['inventory_placement_id' => 1, 'quantity' => 'five']]],
            'reservations.0.quantity',
            'Количество предмета должно быть целым числом.',
        ],
        'quantity must be min 1' => [
            ['reservations' => [['inventory_placement_id' => 1, 'quantity' => 0]]],
            'reservations.0.quantity',
            'Количество предмета не должно быть меньше 1',
        ],
    ]);

    it('rejects an unauthenticated request', function () {
        $this->payload['reservations'] = [
            [
                'inventory_placement_id' => $this->placement->id,
                'quantity' => 2,
            ],
        ];

        $response = $this->postJson(route('events-create'), $this->payload);

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);
    });
});

describe('Events index', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
    });

    it('returns all events', function () {
        $events = Event::factory()->count(3)
            ->state(new Sequence(
                ['starts_at' => '2020-01-01T12:00:00+03:00'],
                ['starts_at' => '2020-01-01T11:00:00+03:00'],
                ['starts_at' => '2020-01-01T09:00:00+03:00']
            ))
            ->create();

        $response = $this->be($this->user)->getJson(route('events-index'));

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'description',
                    'starts_at',
                    'ends_at',
                ],
            ],
        ]);

        $response->assertJsonCount(3, 'data');

        $responseData = collect($response->json('data'));
        $responseEventStarts = $responseData->pluck('starts_at')->values()->all();

        expect($responseEventStarts)->toEqual(collect($responseEventStarts)->sort()->values()->all());

        foreach ($events as $event) {
            $actualEvent = $responseData->where('id', $event->id)->first();

            expect($actualEvent['name'])->toBe($event->name)
                ->and($actualEvent['id'])->toBe($event->id)
                ->and($actualEvent['description'])->toBe($event->description)
                ->and($actualEvent['starts_at'])->toEqual($event->starts_at->toISOString())
                ->and($actualEvent['ends_at'])->toEqual($event->ends_at->toISOString());
        }
    });

    it('returns an empty list when no events exist', function () {
        $response = $this->be($this->user)->getJson(route('events-index'));

        $response->assertStatus(200);

        $response->assertJson([
            'data' => [],
        ]);

        $response->assertJsonCount(0, 'data');
    });

    it('rejects an unauthenticated request', function () {
        $response = $this->getJson(route('events-index'));

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);
    });
});

describe('Events show', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();

        $this->inventory = Inventory::factory()->create([
            'quantity' => 10,
        ]);

        $this->cell = Cell::factory()->create();

        $this->placement = InventoryPlacement::factory()->create([
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->cell->id,
            'quantity' => 10,
        ]);

        $this->travelTo(Carbon::parse('2026-11-05T10:00:00+03:00'));

        $this->startsAt = Carbon::parse('2026-11-05T10:00:00+03:00');
        $this->endsAt = $this->startsAt->clone()->addHours(1);

        $this->event = Event::factory()->create([
            'starts_at' => $this->startsAt->setTimezone('UTC')->format('Y-m-d H:i:s'),
            'ends_at' => $this->endsAt->setTimezone('UTC')->format('Y-m-d H:i:s'),
        ]);
        $this->reservation = EventInventoryReservation::factory()->create([
            'event_id' => $this->event->id,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->cell->id,
            'quantity' => 10,
        ]);
    });

    it('returns an event with its inventory reservations', function () {
        $anotherEvent = Event::factory()->create([
            'starts_at' => $this->startsAt->clone()->addHours(1)->setTimezone('UTC')->format('Y-m-d H:i:s'),
            'ends_at' => $this->endsAt->clone()->addHours(2)->setTimezone('UTC')->format('Y-m-d H:i:s'),
        ]);
        EventInventoryReservation::factory()->create([
            'event_id' => $anotherEvent->id,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->cell->id,
        ]);

        $response = $this->be($this->user)->getJson(route('events-show', $this->event->id));

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'id',
            'name',
            'description',
            'starts_at',
            'ends_at',
            'reservations' => [
                '*' => [
                    'id',
                    'quantity',
                    'inventory' => [
                        'id',
                        'name',
                        'quantity',
                    ],
                    'warehouse' => [
                        'id',
                        'name',
                    ],
                    'rack' => [
                        'id',
                        'code',
                    ],
                    'cell' => [
                        'id',
                        'number',
                        'level',
                        'position',
                    ],
                ],
            ],
        ]);

        $response->assertJsonCount(1, 'reservations');

        $responseData = $response->json();

        expect($responseData['id'])->toBe($this->event->id)
            ->and($responseData['name'])->toBe($this->event->name)
            ->and($responseData['description'])->toBe($this->event->description)
            ->and($responseData['starts_at'])->toEqual($this->event->starts_at->toISOString())
            ->and($responseData['ends_at'])->toEqual($this->event->ends_at->toISOString());

        $this->event->refresh();

        $expectedReservations = $this->event->eventInventoryReservations;

        foreach ($expectedReservations as $reservation) {
            $actualReservation = collect($responseData['reservations'])->firstWhere('id', $reservation->id);

            $rack = $reservation->cell->rack;
            $warehouse = $rack->warehouse;

            expect($actualReservation['id'])->toBe($reservation->id)
                ->and($actualReservation['quantity'])->toBe($reservation->quantity)
                ->and($actualReservation['inventory']['id'])->toBe($reservation->inventory->id)
                ->and($actualReservation['inventory']['name'])->toBe($reservation->inventory->name)
                ->and($actualReservation['inventory']['quantity'])->toBe($reservation->inventory->quantity)
                ->and($actualReservation['warehouse']['id'])->toBe($warehouse->id)
                ->and($actualReservation['warehouse']['name'])->toBe($warehouse->name)
                ->and($actualReservation['rack']['id'])->toBe($rack->id)
                ->and($actualReservation['rack']['code'])->toBe($rack->code)
                ->and($actualReservation['cell']['id'])->toBe($reservation->cell->id)
                ->and($actualReservation['cell']['number'])->toBe($reservation->cell->number)
                ->and($actualReservation['cell']['position'])->toBe($reservation->cell->position)
                ->and($actualReservation['cell']['level'])->toBe($reservation->cell->level);
        }
    });

    it('returns 404 for a nonexistent event', function () {
        $response = $this->be($this->user)->getJson(route('events-show', $this->event->id + 1000));

        $response->assertStatus(404);

        $response->assertJson([
            'message' => 'Событие с переданным ID не найдено.',
        ]);
    });

    it('rejects an unauthenticated request', function () {
        $response = $this->getJson(route('events-show', $this->event->id + 1000));

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);
    });
});

describe('Delete event', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();

        $this->inventory = Inventory::factory()->create([
            'quantity' => 10,
        ]);

        $this->cell = Cell::factory()->create();

        $this->placement = InventoryPlacement::factory()->create([
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->cell->id,
            'quantity' => $this->inventory->quantity,
        ]);

        $this->event = Event::factory()->create();
        $this->reservations = EventInventoryReservation::factory()->count(3)
            ->state(new Sequence(
                [
                    'event_id' => $this->event->id,
                    'inventory_id' => $this->inventory->id,
                    'cell_id' => $this->cell->id,
                    'quantity' => $this->inventory->quantity,
                ],
                ['event_id' => $this->event->id],
                ['event_id' => $this->event->id],
            ))
            ->create();
    });

    it('deletes an event and its reservations', function () {
        $otherEvent = Event::factory()->create();
        $otherReservation = EventInventoryReservation::factory()->create([
            'event_id' => $otherEvent->id,
        ]);

        $inventoryQuantity = $this->inventory->quantity;
        $placementQuantity = $this->placement->quantity;

        $response = $this->be($this->user)->deleteJson(route('events-delete', $this->event->id));

        $response->assertStatus(204);

        assertDatabaseHas('inventories', [
            'id' => $this->inventory->id,
            'quantity' => $inventoryQuantity,
        ]);

        assertDatabaseHas('inventory_placements', [
            'id' => $this->placement->id,
            'quantity' => $placementQuantity,
        ]);

        assertDatabaseHas('events', [
            'id' => $otherEvent->id,
        ]);

        assertDatabaseHas('event_inventory_reservations', [
            'id' => $otherReservation->id,
        ]);

        assertDatabaseMissing('events', [
            'id' => $this->event->id,
        ]);

        foreach ($this->reservations as $reservation) {
            assertDatabaseMissing('event_inventory_reservations', [
                'id' => $reservation->id,
            ]);
        }
    });

    it('returns 404 when deleting a nonexistent event', function () {
        $response = $this->be($this->user)->deleteJson(route('events-delete', $this->event->id + 1000));

        $response->assertStatus(404);

        $response->assertJson([
            'message' => 'Событие с переданным ID не найдено.',
        ]);

        assertDatabaseCount('events', 1);
        assertDatabaseCount('event_inventory_reservations', 3);
    });

    it('rejects an unauthenticated request', function () {
        $response = $this->deleteJson(route('events-delete', $this->event->id));

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);

        assertDatabaseCount('events', 1);
        assertDatabaseCount('event_inventory_reservations', 3);
    });
});

describe('Event update', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();

        $this->inventory = Inventory::factory()->create([
            'quantity' => 10,
        ]);

        $this->placement = InventoryPlacement::factory()->create([
            'inventory_id' => $this->inventory->id,
            'quantity' => 10,
        ]);

        $this->travelTo(Carbon::parse('2026-11-05T10:00:00+03:00')->setTimezone('UTC'));

        $this->startsAt = Carbon::parse('2026-11-05T10:00:00+03:00')->setTimezone('UTC');
        $this->endsAt = $this->startsAt->clone()->addHours(1);
        $this->name = 'Событие';
        $this->description = 'Описание';

        $this->event = Event::factory()->create([
            'name' => $this->name,
            'description' => $this->description,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
        ]);

        $this->reservation = EventInventoryReservation::factory()->create([
            'event_id' => $this->event->id,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->placement->cell_id,
            'quantity' => 5,
        ]);
    });

    it('updates event details and dates', function () {
        $newName = 'newName';
        $newDescription = 'newDescription';
        $newStartsAt = $this->startsAt->clone()->addMinutes(30);
        $newEndsAt = $this->endsAt->clone()->addHours(2);

        $otherEvent = Event::factory()->create([
            'starts_at' => $newStartsAt->clone()->setTimezone('UTC')->format('Y-m-d H:i:s'),
            'ends_at' => $newEndsAt->clone()->setTimezone('UTC')->format('Y-m-d H:i:s'),
        ]);

        $otherReservations = EventInventoryReservation::factory()->create([
            'event_id' => $otherEvent->id,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->placement->cell_id,
            'quantity' => 5,
        ]);

        $response = $this->be($this->user)->patchJson(route('events-update', $this->event->id), [
            'name' => $newName,
            'description' => $newDescription,
            'starts_at' => $newStartsAt->toIso8601String(),
            'ends_at' => $newEndsAt->toIso8601String(),
        ]);

        $response->assertStatus(200);

        $response->assertJson([
            'name' => $newName,
            'description' => $newDescription,
            'starts_at' => $newStartsAt->toISOString(),
            'ends_at' => $newEndsAt->toISOString(),
        ]);

        assertDatabaseHas('events', [
            'id' => $this->event->id,
            'name' => $newName,
            'description' => $newDescription,
            'starts_at' => $newStartsAt->clone()->setTimezone('UTC')->format('Y-m-d H:i:s'),
            'ends_at' => $newEndsAt->clone()->setTimezone('UTC')->format('Y-m-d H:i:s'),
        ]);

        assertDatabaseHas('events', [
            'id' => $otherEvent->id,
            'name' => $otherEvent->name,
            'description' => $otherEvent->description,
            'starts_at' => $otherEvent->starts_at,
            'ends_at' => $otherEvent->ends_at,
        ]);
    });

    it('reschedules an event with reservations in multiple cells', function () {
        $this->inventory->update(['quantity' => 15]);

        $otherCell = Cell::factory()->create();

        $otherPlacement = InventoryPlacement::factory()->create([
            'inventory_id' => $this->inventory->id,
            'cell_id' => $otherCell->id,
            'quantity' => 5,
        ]);

        $otherReservations = EventInventoryReservation::factory()->create([
            'event_id' => $this->event->id,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $otherCell->id,
            'quantity' => 5,
        ]);

        $newStartsAt = $this->startsAt->clone()->addMinutes(30);
        $newEndsAt = $this->endsAt->clone()->addHours(2);

        $response = $this->be($this->user)->patchJson(route('events-update', $this->event->id), [
            'starts_at' => $newStartsAt->toIso8601String(),
            'ends_at' => $newEndsAt->toIso8601String(),
        ]);

        $response->assertStatus(200);

        assertDatabaseHas('events', [
            'id' => $this->event->id,
            'starts_at' => $newStartsAt->clone()->setTimezone('UTC')->format('Y-m-d H:i:s'),
            'ends_at' => $newEndsAt->clone()->setTimezone('UTC')->format('Y-m-d H:i:s'),
        ]);

        assertDatabaseHas('event_inventory_reservations', [
            'id' => $this->reservation->id,
            'inventory_id' => $this->reservation->inventory_id,
            'cell_id' => $this->reservation->cell_id,
            'quantity' => 5,
        ]);

        assertDatabaseHas('event_inventory_reservations', [
            'id' => $otherReservations->id,
            'event_id' => $this->event->id,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $otherCell->id,
            'quantity' => 5,
        ]);
    });

    it('updates only the start time', function () {
        $newStartsAt = $this->startsAt->clone()->addMinutes(30);
        $newEndsAt = $this->endsAt->clone()->addHours(2);

        $otherEvent = Event::factory()->create([
            'starts_at' => $this->startsAt->clone()->setTimezone('UTC')->format('Y-m-d H:i:s'),
            'ends_at' => $newEndsAt->clone()->setTimezone('UTC')->format('Y-m-d H:i:s'),
        ]);

        $otherReservations = EventInventoryReservation::factory()->create([
            'event_id' => $otherEvent->id,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->placement->cell_id,
            'quantity' => 5,
        ]);

        $response = $this->be($this->user)->patchJson(route('events-update', $this->event->id), [
            'starts_at' => $newStartsAt->toIso8601String(),
        ]);

        $response->assertStatus(200);

        $response->assertJson([
            'id' => $this->event->id,
            'name' => $this->event->name,
            'description' => $this->event->description,
            'starts_at' => $newStartsAt->toISOString(),
            'ends_at' => $this->event->ends_at->toISOString(),
        ]);

        assertDatabaseHas('events', [
            'id' => $this->event->id,
            'starts_at' => $newStartsAt->clone()->setTimezone('UTC')->format('Y-m-d H:i:s'),
        ]);
    });

    it('updates only the end time', function () {
        $newEndsAt = $this->endsAt->clone()->addHours(2);

        $otherEvent = Event::factory()->create([
            'starts_at' => $this->startsAt->clone()->setTimezone('UTC')->format('Y-m-d H:i:s'),
            'ends_at' => $newEndsAt->clone()->setTimezone('UTC')->format('Y-m-d H:i:s'),
        ]);

        $otherReservations = EventInventoryReservation::factory()->create([
            'event_id' => $otherEvent->id,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->placement->cell_id,
            'quantity' => 5,
        ]);

        $response = $this->be($this->user)->patchJson(route('events-update', $this->event->id), [
            'ends_at' => $newEndsAt->toIso8601String(),
        ]);

        $response->assertStatus(200);

        $response->assertJson([
            'id' => $this->event->id,
            'name' => $this->event->name,
            'description' => $this->event->description,
            'starts_at' => $this->event->starts_at->toISOString(),
            'ends_at' => $newEndsAt->toISOString(),
        ]);

        assertDatabaseHas('events', [
            'id' => $this->event->id,
            'ends_at' => $newEndsAt->clone()->setTimezone('UTC')->format('Y-m-d H:i:s'),
        ]);
    });

    it('updates only the name', function () {
        $newName = 'newName';

        $response = $this->be($this->user)->patchJson(route('events-update', $this->event->id), [
            'name' => $newName,
        ]);

        $response->assertStatus(200);

        $response->assertJson([
            'id' => $this->event->id,
            'name' => $newName,
            'description' => $this->event->description,
            'starts_at' => $this->event->starts_at->toISOString(),
            'ends_at' => $this->event->ends_at->toISOString(),
        ]);

        assertDatabaseHas('events', [
            'id' => $this->event->id,
            'name' => $newName,
        ]);
    });

    it('updates the description', function () {
        $desc = 'newDesc';

        $response = $this->be($this->user)->patchJson(route('events-update', $this->event->id), [
            'description' => $desc,
        ]);

        $response->assertStatus(200);

        $response->assertJson([
            'id' => $this->event->id,
            'name' => $this->event->name,
            'description' => $desc,
            'starts_at' => $this->event->starts_at->toISOString(),
            'ends_at' => $this->event->ends_at->toISOString(),
        ]);

        assertDatabaseHas('events', [
            'id' => $this->event->id,
            'description' => $desc,
        ]);
    });

    it('clears the description', function () {
        $desc = null;

        $response = $this->be($this->user)->patchJson(route('events-update', $this->event->id), [
            'description' => $desc,
        ]);

        $response->assertStatus(200);

        $response->assertJson([
            'id' => $this->event->id,
            'name' => $this->event->name,
            'description' => $desc,
            'starts_at' => $this->event->starts_at->toISOString(),
            'ends_at' => $this->event->ends_at->toISOString(),
        ]);

        assertDatabaseHas('events', [
            'id' => $this->event->id,
            'description' => $desc,
        ]);
    });

    it('rejects a start time equal to the existing end time', function () {
        $response = $this->be($this->user)->patchJson(route('events-update', $this->event->id), [
            'starts_at' => $this->startsAt->clone()->addHours(1)->toIso8601String(),
        ]);

        $response->assertStatus(409);

        $response->assertJson([
            'message' => 'Дата начала не может быть позже или равна дате окончания события',
        ]);

        assertDatabaseHas('events', [
            'id' => $this->event->id,
            'starts_at' => $this->event->starts_at,
        ]);
    });

    it('rejects an end time equal to the existing start time', function () {
        $response = $this->be($this->user)->patchJson(route('events-update', $this->event->id), [
            'name' => 'newName',
            'ends_at' => $this->event->starts_at->toIso8601String(),
        ]);

        $response->assertStatus(409);

        $response->assertJson([
            'message' => 'Дата начала не может быть позже или равна дате окончания события',
        ]);

        assertDatabaseHas('events', [
            'id' => $this->event->id,
            'name' => $this->event->name,
            'ends_at' => $this->event->ends_at,
        ]);
    });

    it('rejects rescheduling when stock is insufficient', function () {
        $newEndsAt = $this->endsAt->clone()->addHours(4);

        $otherEvent = Event::factory()->create([
            'starts_at' => $this->endsAt->clone()->addHours(2)->setTimezone('UTC')->format('Y-m-d H:i:s'),
            'ends_at' => $newEndsAt->clone()->setTimezone('UTC')->format('Y-m-d H:i:s'),
        ]);

        $otherReservations = EventInventoryReservation::factory()->create([
            'event_id' => $otherEvent->id,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->placement->cell_id,
            'quantity' => 6,
        ]);

        $response = $this->be($this->user)->patchJson(route('events-update', $this->event->id), [
            'name' => 'newName',
            'ends_at' => $newEndsAt->toIso8601String(),
        ]);

        $response->assertStatus(409);

        assertDatabaseHas('events', [
            'id' => $this->event->id,
            'name' => $this->event->name,
            'ends_at' => $this->event->ends_at,
        ]);
    });

    it('rejects rescheduling when a reserved placement is missing', function () {
        $emptyCell = Cell::factory()->create();

        $otherReservation = EventInventoryReservation::factory()->create([
            'event_id' => $this->event->id,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $emptyCell->id,
            'quantity' => 5,
        ]);

        $newStartsAt = $this->startsAt->clone()->addMinutes(30);

        $response = $this->be($this->user)->patchJson(route('events-update', $this->event->id), [
            'starts_at' => $newStartsAt->toIso8601String(),
        ]);

        $response->assertStatus(404);

        $response->assertJson([
            'message' => 'Размещение товара '.$this->inventory->id.' не найдено в ячейке '.$emptyCell->id,
        ]);

        assertDatabaseHas('events', [
            'id' => $this->event->id,
            'starts_at' => $this->event->starts_at,
        ]);
    });

    it('returns 404 when the event does not exist', function () {
        $newName = 'newName';

        $response = $this->be($this->user)->patchJson(route('events-update', $this->event->id + 1000), [
            'name' => $newName,
        ]);

        $response->assertStatus(404);

        $response->assertJson([
            'message' => 'Событие с переданным ID не найдено.',
        ]);

        assertDatabaseHas('events', [
            'id' => $this->event->id,
            'name' => $this->event->name,
        ]);
    });

    it('rejects invalid event update data', function (array $payload, string $errorKey, string $expectedMessage) {
        $response = $this->be($this->user)->patchJson(route('events-update', $this->event->id), $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                $errorKey => $expectedMessage,
            ]);

        assertDatabaseHas('events', [
            'id' => $this->event->id,
            'name' => $this->event->name,
            'description' => $this->event->description,
            'starts_at' => $this->event->starts_at,
            'ends_at' => $this->event->ends_at,
        ]);
    })->with([
        'name must be string' => [
            ['name' => 12345],
            'name',
            'Название бронирования должно быть строкой.',
        ],
        'name exceeds max length' => [
            ['name' => str_repeat('A', 256)],
            'name',
            'Название бронирования не должно превышать 255 символов.',
        ],
        'description must be string' => [
            ['description' => 12345],
            'description',
            'Описание должно быть текстовой строкой.',
        ],
        'description exceeds max length' => [
            ['description' => str_repeat('B', 256)],
            'description',
            'Описание не должно превышать 255 символов.',
        ],
        'starts_at incorrect ISO format' => [
            ['starts_at' => '2026-11-05 10:00:00'],
            'starts_at',
            'Указан неверный формат даты начала. Ожидается ISO 8601 (например, 2026-11-05T10:00:00+03:00).',
        ],
        'starts_at cannot be in past' => [
            ['starts_at' => '2025-01-01T10:00:00+03:00'],
            'starts_at',
            'Дата начала резервирования не может быть в прошлом.',
        ],
        'ends_at incorrect ISO format' => [
            ['ends_at' => '2026-11-05 11:00:00'],
            'ends_at',
            'Указан неверный формат даты окончания. Ожидается ISO 8601 (например, 2026-11-05T11:00:00+03:00).',
        ],
    ]);

    it('rejects an unauthenticated request', function () {
        $response = $this->patchJson(route('events-update', $this->event->id), [
            'name' => 'newName',
        ]);

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);

        assertDatabaseHas('events', [
            'id' => $this->event->id,
            'name' => $this->event->name,
        ]);
    });
});
