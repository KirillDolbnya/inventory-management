<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\RackCodeAlreadyTakenException;
use App\Exceptions\RackNotFoundException;
use App\Exceptions\WarehouseNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\RackCreateRequest;
use App\Http\Requests\RackUpdateRequest;
use App\Http\Resources\RackCollection;
use App\UseCases\Rack\Create\CreateRackItemInput;
use App\UseCases\Rack\Create\CreateRacks;
use App\UseCases\Rack\Create\CreateRacksInput;
use App\UseCases\Rack\Delete\DeleteRack;
use App\UseCases\Rack\Delete\DeleteRackInput;
use App\UseCases\Rack\QueryList\QueryListRack;
use App\UseCases\Rack\QueryList\QueryListRackInput;
use App\UseCases\Rack\Update\UpdateRack;
use App\UseCases\Rack\Update\UpdateRackInput;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RackController extends Controller
{
    /**
     * @throws \Throwable
     * @throws WarehouseNotFoundException
     * @throws RackCodeAlreadyTakenException
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
    public function store(RackCreateRequest $request, CreateRacks $createRacks): JsonResponse
    {
        $racksData = $request->input('racks');

        $rackInputs = array_map(fn ($rackData) => new CreateRackItemInput($rackData['code'], $rackData['levels_count'], $rackData['cells_per_level']), $racksData);

        $input = new CreateRacksInput((int) $request->route('warehouseId'), $rackInputs);

        $output = ($createRacks)($input);

        return (new RackCollection($output->racks))->response()->setStatusCode(201);
    }

    /**
     * @throws WarehouseNotFoundException
     */
    #[ScrambleResponse(
        status: 404,
        description: 'Resource not found',
        type: 'array{message: string}'
    )]
    public function index(Request $request, QueryListRack $queryListRack): RackCollection
    {
        $input = new QueryListRackInput((int) $request->route('warehouseId'));

        $output = ($queryListRack)($input);

        return new RackCollection($output->racks);
    }

    /**
     * @throws \Throwable
     * @throws RackNotFoundException
     * @throws WarehouseNotFoundException
     */
    #[ScrambleResponse(
        status: 404,
        description: 'Resource not found',
        type: 'array{message: string}'
    )]
    #[ScrambleResponse(
        status: 409,
        description: 'Resource state conflict or business logic violation.',
        type: 'array{message: string}'
    )]
    public function delete(Request $request, DeleteRack $deleteRack): Response
    {
        $input = new DeleteRackInput((int) $request->route('warehouseId'), (int) $request->route('rackId'));

        ($deleteRack)($input);

        return response()->noContent();
    }

    /**
     * @throws \Throwable
     * @throws RackNotFoundException
     * @throws WarehouseNotFoundException
     */
    #[ScrambleResponse(
        status: 404,
        description: 'Resource not found',
        type: 'array{message: string}'
    )]
    #[ScrambleResponse(
        status: 409,
        description: 'Resource state conflict or business logic violation.',
        type: 'array{message: string}'
    )]
    public function update(RackUpdateRequest $request, UpdateRack $updateRack): JsonResponse
    {
        $input = new UpdateRackInput(
            (int) $request->route('warehouseId'),
            (int) $request->route('rackId'),
            $request->has('code') ? $request->string('code')->toString() : null,
            $request->has('levels_count') ? (int) $request->input('levels_count') : null,
            $request->has('cells_per_level') ? (int) $request->input('cells_per_level') : null,
        );

        $output = ($updateRack)($input);

        return response()->json([
            'id' => $output->rackId,
            'code' => $output->rackCode,
            'levels_count' => $output->levelsCount,
            'cells_per_level' => $output->cellsPerLevel,
            'cells' => $output->cells,
        ]);
    }
}
