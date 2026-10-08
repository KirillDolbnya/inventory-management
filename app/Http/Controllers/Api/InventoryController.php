<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\CellNotFoundException;
use App\Exceptions\InventoryNameAlreadyTakenException;
use App\Http\Controllers\Controller;
use App\Http\Requests\InventoryCreateRequest;
use App\UseCases\Inventory\Create\CreateInventory;
use App\UseCases\Inventory\Create\CreateInventoryInput;
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
}
