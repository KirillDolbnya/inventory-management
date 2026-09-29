<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\FiasIdAlreadyTakenException;
use App\Exceptions\FiasIdNotFoundException;
use App\Exceptions\WarehouseNameAlreadyTakenException;
use App\Exceptions\WarehouseNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\WarehouseCreateRequest;
use App\Http\Requests\WarehouseUpdateRequest;
use App\UseCases\Warehouse\Create\CreateWarehouse;
use App\UseCases\Warehouse\Create\CreateWarehouseInput;
use App\UseCases\Warehouse\Update\UpdateWarehouse;
use App\UseCases\Warehouse\Update\UpdateWarehouseInput;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Http\JsonResponse;

class WarehouseController extends Controller
{
    /**
     * @throws WarehouseNameAlreadyTakenException
     * @throws FiasIdAlreadyTakenException
     * @throws FiasIdNotFoundException
     */
    #[ScrambleResponse(
        status: 422,
        description: 'Validation error',
        type: 'array{message: string, errors: array<string, string[]>}'
    )]
    public function store(WarehouseCreateRequest $request, CreateWarehouse $createWarehouse): JsonResponse
    {
        $input = new CreateWarehouseInput($request->string('name')->toString(), $request->string('fias_id')->toString());

        $output = ($createWarehouse)($input);

        return response()->json(['id' => $output->id, 'name' => $output->name, 'address' => $output->address, 'latitude' => $output->latitude, 'longitude' => $output->longitude], 201);
    }

    /**
     * @throws FiasIdNotFoundException
     * @throws WarehouseNameAlreadyTakenException
     * @throws FiasIdAlreadyTakenException
     * @throws WarehouseNotFoundException
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
    public function update(WarehouseUpdateRequest $request, UpdateWarehouse $updateWarehouse): JsonResponse
    {
        $input = new UpdateWarehouseInput((int) $request->route('warehouseId'), $request->has('name') ? $request->string('name')->toString() : null, $request->has('fias_id') ? $request->string('fias_id')->toString() : null);

        $output = ($updateWarehouse)($input);

        return response()->json(['id' => $output->id, 'name' => $output->name, 'address' => $output->address, 'latitude' => $output->latitude, 'longitude' => $output->longitude], 200);
    }
}
