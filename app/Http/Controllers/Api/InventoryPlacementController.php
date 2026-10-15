<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\CellNotFoundException;
use App\Exceptions\InsufficientPlacementQuantityException;
use App\Exceptions\InventoryNotFoundException;
use App\Exceptions\InventoryPlacementNotFoundException;
use App\Exceptions\InventoryPlacementQuantityExceededException;
use App\Http\Controllers\Controller;
use App\Http\Requests\InventoryPlacementRequest;
use App\Http\Requests\InventoryUnplaceRequest;
use App\UseCases\Inventory\Place\PlaceInventory;
use App\UseCases\Inventory\Place\PlaceInventoryInput;
use App\UseCases\Inventory\Unplace\UnplaceInventory;
use App\UseCases\Inventory\Unplace\UnplaceInventoryInput;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Http\JsonResponse;

class InventoryPlacementController extends Controller
{
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

    /**
     * @throws InventoryNotFoundException
     * @throws InventoryPlacementNotFoundException
     * @throws InsufficientPlacementQuantityException
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
    public function unplace(InventoryUnplaceRequest $request, UnplaceInventory $unplaceInventory): JsonResponse
    {
        $input = new UnplaceInventoryInput((int) $request->route('inventoryPlacementId'), $request->integer('quantity'));

        $output = ($unplaceInventory)($input);

        return response()->json([
            'inventory_id' => $output->inventoryId,
            'removed_quantity' => $output->removedQuantity,
            'placement_quantity' => $output->placementQuantity,
            'placed_quantity' => $output->placedQuantity,
            'unplaced_quantity' => $output->unplacedQuantity,
        ]);
    }
}
