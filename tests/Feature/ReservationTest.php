<?php

use App\Models\Event;
use App\Models\EventInventoryReservation;
use App\Models\Inventory;
use App\Models\InventoryPlacement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

uses(RefreshDatabase::class);

describe('Reservation add', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();

        $this->inventory = Inventory::factory()->create([
            'quantity' => 15,
        ]);

        $this->placement = InventoryPlacement::factory()->create([
            'inventory_id' => $this->inventory->id,
            'quantity' => 15,
        ]);

        $this->travelTo(Carbon::parse('2026-11-05T10:00:00+03:00')->setTimezone('UTC'));
        $this->startsAt = Carbon::parse('2026-11-05T10:00:00+03:00');
        $this->endsAt = $this->startsAt->clone()->addHours(1);

        $this->event = Event::factory()->create([
            'starts_at' => $this->startsAt->setTimezone('UTC')->format('Y-m-d H:i:s'),
            'ends_at' => $this->endsAt->setTimezone('UTC')->format('Y-m-d H:i:s'),
        ]);
    });

    it('adds multiple reservations to an existing event', function () {
        $otherInventory = Inventory::factory()->create([
            'quantity' => 3,
        ]);

        $otherPlacement = InventoryPlacement::factory()->create([
            'inventory_id' => $otherInventory->id,
            'quantity' => 3,
        ]);

        $response = $this->be($this->user)->postJson(route('events-reservations-add', $this->event->id), [
            'reservations' => [
                [
                    'inventory_placement_id' => $otherPlacement->id,
                    'quantity' => 3,
                ],
                [
                    'inventory_placement_id' => $this->placement->id,
                    'quantity' => 4,
                ],
            ],
        ]);

        $response->assertStatus(201);

        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'event_id',
                    'inventory_id',
                    'cell_id',
                    'quantity',
                ],
            ],
        ]);

        $response->assertJsonCount(2, 'data');

        assertDatabaseCount('event_inventory_reservations', 2);

        assertDatabaseHas('event_inventory_reservations', [
            'event_id' => $this->event->id,
            'inventory_id' => $otherInventory->id,
            'cell_id' => $otherPlacement->cell_id,
            'quantity' => 3,
        ]);

        assertDatabaseHas('event_inventory_reservations', [
            'event_id' => $this->event->id,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->placement->cell_id,
            'quantity' => 4,
        ]);
    });

    it('rejects adding a placement already reserved for the event', function () {
        $otherReservation = EventInventoryReservation::factory()->create([
            'event_id' => $this->event->id,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->placement->cell_id,
            'quantity' => 3,
        ]);

        $response = $this->be($this->user)->postJson(route('events-reservations-add', $this->event->id), [
            'reservations' => [
                [
                    'inventory_placement_id' => $this->placement->id,
                    'quantity' => 4,
                ],
            ],
        ]);

        $response->assertStatus(409);

        assertDatabaseCount('event_inventory_reservations', 1);

        assertDatabaseHas('event_inventory_reservations', [
            'id' => $otherReservation->id,
            'quantity' => $otherReservation->quantity,
        ]);
    });

    it('returns 404 when a placement does not exist', function () {
        $response = $this->be($this->user)->postJson(route('events-reservations-add', $this->event->id), [
            'reservations' => [
                [
                    'inventory_placement_id' => $this->placement->id + 10000,
                    'quantity' => 3,
                ],
            ],
        ]);

        $response->assertStatus(404);

        assertDatabaseCount('event_inventory_reservations', 0);
    });

    it('rejects the entire batch when one reservation exceeds availability', function () {
        $otherInventory = Inventory::factory()->create([
            'quantity' => 2,
        ]);

        $otherPlacement = InventoryPlacement::factory()->create([
            'inventory_id' => $otherInventory->id,
            'quantity' => 2,
        ]);

        $otherEvent = Event::factory()->create([
            'starts_at' => $this->startsAt->setTimezone('UTC')->format('Y-m-d H:i:s'),
            'ends_at' => $this->endsAt->setTimezone('UTC')->format('Y-m-d H:i:s'),
        ]);

        EventInventoryReservation::factory()->create([
            'event_id' => $otherEvent->id,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->placement->cell_id,
            'quantity' => 12,
        ]);

        $response = $this->be($this->user)->postJson(route('events-reservations-add', $this->event->id), [
            'reservations' => [
                [
                    'inventory_placement_id' => $otherPlacement->id,
                    'quantity' => 2,
                ],
                [
                    'inventory_placement_id' => $this->placement->id,
                    'quantity' => 4,
                ],
            ],
        ]);

        $response->assertStatus(409);

        assertDatabaseCount('event_inventory_reservations', 1);

        assertDatabaseMissing('event_inventory_reservations', [
            'event_id' => $this->event->id,
            'inventory_id' => $otherInventory->id,
            'cell_id' => $otherPlacement->cell_id,
            'quantity' => 2,
        ]);

        assertDatabaseMissing('event_inventory_reservations', [
            'event_id' => $this->event->id,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->placement->cell_id,
            'quantity' => 4,
        ]);
    });

    it('rejects a quantity greater than the placement stock', function () {
        $response = $this->be($this->user)->postJson(route('events-reservations-add', $this->event->id), [
            'reservations' => [
                [
                    'inventory_placement_id' => $this->placement->id,
                    'quantity' => 20,
                ],
            ],
        ]);

        $response->assertStatus(409);

        assertDatabaseCount('event_inventory_reservations', 0);
    });

    it('rejects invalid reservation addition data', function (array $payload, string $errorKey, string $expectedMessage) {
        $response = $this->be($this->user)->postJson(route('events-reservations-add', $this->event->id), $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                $errorKey => $expectedMessage,
            ]);

        assertDatabaseCount('event_inventory_reservations', 0);
    })->with([
        'missing reservations' => [
            [],
            'reservations',
            'Список резервируемых позиций обязателен.',
        ],
        'reservations is null' => [
            ['reservations' => null],
            'reservations',
            'Список резервируемых позиций обязателен.',
        ],
        'reservations is not a list (associative array)' => [
            [
                'reservations' => [
                    'first' => ['inventory_placement_id' => 1, 'quantity' => 5],
                ],
            ],
            'reservations',
            'Список позиций должен быть последовательным массивом.',
        ],
        'reservations is not a list (scalar)' => [
            ['reservations' => 'invalid'],
            'reservations',
            'Список позиций должен быть последовательным массивом.',
        ],
        'missing inventory_placement_id' => [
            [
                'reservations' => [
                    ['quantity' => 5],
                ],
            ],
            'reservations.0.inventory_placement_id',
            'ID размещения ячейки обязательно для заполнения.',
        ],
        'inventory_placement_id is null' => [
            [
                'reservations' => [
                    ['inventory_placement_id' => null, 'quantity' => 5],
                ],
            ],
            'reservations.0.inventory_placement_id',
            'ID размещения ячейки обязательно для заполнения.',
        ],
        'inventory_placement_id is not integer (string)' => [
            [
                'reservations' => [
                    ['inventory_placement_id' => 'abc', 'quantity' => 5],
                ],
            ],
            'reservations.0.inventory_placement_id',
            'ID размещения должно быть целым числом.',
        ],
        'inventory_placement_id is not integer (float)' => [
            [
                'reservations' => [
                    ['inventory_placement_id' => 1.5, 'quantity' => 5],
                ],
            ],
            'reservations.0.inventory_placement_id',
            'ID размещения должно быть целым числом.',
        ],
        'inventory_placement_id is less than 1 (zero)' => [
            [
                'reservations' => [
                    ['inventory_placement_id' => 0, 'quantity' => 5],
                ],
            ],
            'reservations.0.inventory_placement_id',
            'Неверный ID размещения ячейки.',
        ],
        'inventory_placement_id is negative' => [
            [
                'reservations' => [
                    ['inventory_placement_id' => -3, 'quantity' => 5],
                ],
            ],
            'reservations.0.inventory_placement_id',
            'Неверный ID размещения ячейки.',
        ],
        'duplicate inventory_placement_id' => [
            [
                'reservations' => [
                    ['inventory_placement_id' => 1, 'quantity' => 2],
                    ['inventory_placement_id' => 1, 'quantity' => 4],
                ],
            ],
            'reservations.0.inventory_placement_id',
            'В списке позиций обнаружены дубликаты одинаковых ячеек.',
        ],
        'missing quantity' => [
            [
                'reservations' => [
                    ['inventory_placement_id' => 1],
                ],
            ],
            'reservations.0.quantity',
            'Количество предмета обязательно для заполнения.',
        ],
        'quantity is null' => [
            [
                'reservations' => [
                    ['inventory_placement_id' => 1, 'quantity' => null],
                ],
            ],
            'reservations.0.quantity',
            'Количество предмета обязательно для заполнения.',
        ],
        'quantity is not integer (string)' => [
            [
                'reservations' => [
                    ['inventory_placement_id' => 1, 'quantity' => 'many'],
                ],
            ],
            'reservations.0.quantity',
            'Количество предмета должно быть целым числом.',
        ],
        'quantity is not integer (float)' => [
            [
                'reservations' => [
                    ['inventory_placement_id' => 1, 'quantity' => 2.5],
                ],
            ],
            'reservations.0.quantity',
            'Количество предмета должно быть целым числом.',
        ],
        'quantity is less than 1 (zero)' => [
            [
                'reservations' => [
                    ['inventory_placement_id' => 1, 'quantity' => 0],
                ],
            ],
            'reservations.0.quantity',
            'Количество предмета не должно быть меньше 1',
        ],
        'quantity is negative' => [
            [
                'reservations' => [
                    ['inventory_placement_id' => 1, 'quantity' => -1],
                ],
            ],
            'reservations.0.quantity',
            'Количество предмета не должно быть меньше 1',
        ],
    ]);

    it('returns 404 when the event does not exist', function () {
        $response = $this->be($this->user)->postJson(route('events-reservations-add', $this->event->id + 1000), [
            'reservations' => [
                [
                    'inventory_placement_id' => $this->placement->id,
                    'quantity' => 3,
                ],
            ],
        ]);

        $response->assertStatus(404);

        $response->assertJson([
            'message' => 'Событие с переданным ID не найдено.',
        ]);

        assertDatabaseCount('event_inventory_reservations', 0);

        assertDatabaseMissing('event_inventory_reservations', [
            'event_id' => $this->event->id,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->placement->cell_id,
            'quantity' => 3,
        ]);
    });

    it('rejects an unauthenticated request', function () {
        $response = $this->postJson(route('events-reservations-add', $this->event->id), [
            'reservations' => [
                [
                    'inventory_placement_id' => $this->placement->id,
                    'quantity' => 3,
                ],
            ],
        ]);

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);

        assertDatabaseCount('event_inventory_reservations', 0);

        assertDatabaseMissing('event_inventory_reservations', [
            'event_id' => $this->event->id,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->placement->cell_id,
            'quantity' => 3,
        ]);
    });
});
