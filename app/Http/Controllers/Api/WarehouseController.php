<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\FiasIdAlreadyTakenException;
use App\Exceptions\FiasIdNotFoundException;
use App\Exceptions\WarehouseNameAlreadyTakenException;
use App\Http\Controllers\Controller;
use App\Http\Requests\WarehouseCreateRequest;
use App\UseCases\Warehouse\Create\CreateWarehouse;
use App\UseCases\Warehouse\Create\CreateWarehouseInput;
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
}
