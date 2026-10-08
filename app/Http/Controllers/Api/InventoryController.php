<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\CellNotFoundException;
use App\Exceptions\InventoryNameAlreadyTakenException;
use App\Exceptions\InventoryNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\InventoryCreateRequest;
use App\Http\Requests\InventoryUpdateRequest;
use App\UseCases\Inventory\Create\CreateInventory;
use App\UseCases\Inventory\Create\CreateInventoryInput;
use App\UseCases\Inventory\Update\UpdateInventory;
use App\UseCases\Inventory\Update\UpdateInventoryInput;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Http\JsonResponse;

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
}
