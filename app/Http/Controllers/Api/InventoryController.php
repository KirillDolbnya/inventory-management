<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\CellNotFoundException;
use App\Exceptions\InventoryNameAlreadyTakenException;
use App\Exceptions\InventoryNotFoundException;
use App\Exceptions\InventoryPlacementQuantityExceededException;
use App\Http\Controllers\Controller;
use App\Http\Requests\InventoryCreateRequest;
use App\Http\Requests\InventoryPlacementRequest;
use App\Http\Requests\InventoryUpdateRequest;
use App\UseCases\Inventory\Create\CreateInventory;
use App\UseCases\Inventory\Create\CreateInventoryInput;
use App\UseCases\Inventory\Place\PlaceInventory;
use App\UseCases\Inventory\Place\PlaceInventoryInput;
use App\UseCases\Inventory\QueryDetail\QueryDetailInventory;
use App\UseCases\Inventory\QueryDetail\QueryDetailInventoryInput;
use App\UseCases\Inventory\QueryDetail\QueryDetailInventoryPlacementOutput;
use App\UseCases\Inventory\QueryList\QueryListInventory;
use App\UseCases\Inventory\QueryList\QueryListInventoryItemOutput;
use App\UseCases\Inventory\QueryList\QueryListInventoryPlacementOutput;
use App\UseCases\Inventory\Update\UpdateInventory;
use App\UseCases\Inventory\Update\UpdateInventoryInput;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    /**
     * @throws CellNotFoundException
     * @throws \Throwable
     * @throws InventoryNameAlreadyTakenException
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
    public function store(InventoryCreateRequest $request, CreateInventory $createInventory): JsonResponse
    {
        $input = new CreateInventoryInput(
            $request->string('name')->toString(),
            $request->integer('quantity'),
            $request->filled('description') ? $request->string('description')->toString() : null,
            $request->filled('cell_id') ? $request->integer('cell_id') : null
        );

        $output = ($createInventory)($input);

        return response()->json([
            'id' => $output->id,
            'name' => $output->name,
            'description' => $output->description,
            'quantity' => $output->quantity,
        ], 201);
    }

    /**
     * @throws InventoryNotFoundException
     * @throws InventoryNameAlreadyTakenException
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
    public function update(InventoryUpdateRequest $request, UpdateInventory $updateInventory): JsonResponse
    {
        $input = new UpdateInventoryInput(
            (int) $request->route('inventoryId'),
            $request->has('name') ? $request->string('name')->toString() : null,
            $request->filled('description') ? $request->string('description')->toString() : null,
            $request->has('description')
        );

        $output = ($updateInventory)($input);

        return response()->json([
            'id' => $output->id,
            'name' => $output->name,
            'description' => $output->description,
            'quantity' => $output->quantity,
        ]);
    }

    /**
     * @throws InventoryNotFoundException
     */
    #[ScrambleResponse(
        status: 404,
        description: 'Resource not found',
        type: 'array{message: string}'
    )]
    public function show(Request $request, QueryDetailInventory $queryDetailInventory): JsonResponse
    {
        $input = new QueryDetailInventoryInput((int) $request->route('inventoryId'));

        $output = ($queryDetailInventory)($input);

        return response()->json([
            'id' => $output->id,
            'name' => $output->name,
            'description' => $output->description,
            'quantity' => $output->quantity,
            /**
             * @var list<array{
             *     id: int,
             *     cell_id: int,
             *     quantity: int
             * }>
             */
            'placements' => array_map(fn (QueryDetailInventoryPlacementOutput $placement) => [
                'id' => $placement->id,
                'cell_id' => $placement->cellId,
                'quantity' => $placement->quantity,
            ], $output->placements),
        ]);
    }

    public function index(QueryListInventory $queryListInventory): JsonResponse
    {
        $output = ($queryListInventory)();

        $outputData = array_map(fn (QueryListInventoryItemOutput $item) => [
            'id' => $item->id,
            'name' => $item->name,
            'description' => $item->description,
            'quantity' => $item->quantity,
            'placed_quantity' => $item->placedQuantity,
            'unplaced_quantity' => $item->unplacedQuantity,
            'placements' => array_map(fn (QueryListInventoryPlacementOutput $placement) => [
                'id' => $placement->id,
                'quantity' => $placement->quantity,
                'warehouse' => [
                    'id' => $placement->location->warehouseId,
                    'name' => $placement->location->warehouseName,
                ],
                'rack' => [
                    'id' => $placement->location->rackId,
                    'code' => $placement->location->rackCode,
                ],
                'cell' => [
                    'id' => $placement->location->cellId,
                    'number' => $placement->location->cellNumber,
                    'level' => $placement->location->cellLevel,
                    'position' => $placement->location->cellPosition,
                ],
            ], $item->placements),
        ], $output->items);

        return response()->json([
            /**
             * @var list<array{
             *     id: int,
             *     name: string,
             *     description: string|null,
             *     quantity: int,
             *     placed_quantity: int,
             *     unplaced_quantity: int,
             *     placements: list<array{
             *         id: int,
             *         quantity: int,
             *         warehouse: array{
             *             id: int,
             *             name: string
             *         },
             *         rack: array{
             *             id: int,
             *             code: string
             *         },
             *         cell: array{
             *             id: int,
             *             number: int,
             *             level: int,
             *             position: int
             *         }
             *     }>
             * }>
             */
            'data' => $outputData,
        ]);
    }

    /**
     * @throws CellNotFoundException
     * @throws InventoryPlacementQuantityExceededException
     * @throws InventoryNotFoundException
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
    public function place(InventoryPlacementRequest $request, PlaceInventory $placeInventory): JsonResponse
    {
        $input = new PlaceInventoryInput((int) $request->route('inventoryId'), $request->integer('cell_id'), $request->integer('quantity'));

        $output = ($placeInventory)($input);

        return response()->json([
            'id' => $output->placementId,
            'inventory_id' => $output->inventoryId,
            'cell_id' => $output->cellId,
            'inventory_quantity' => $output->inventoryQuantity,
            'placement_quantity' => $output->placementQuantity,
            'placed_quantity' => $output->placedQuantity,
            'unplaced_quantity' => $output->unplacedQuantity,
        ]);
    }
}
