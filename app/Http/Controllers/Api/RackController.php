<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\RackCodeAlreadyTakenException;
use App\Exceptions\WarehouseNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\RackCreateRequest;
use App\Http\Resources\RackCollection;
use App\UseCases\Rack\Create\CreateRackItemInput;
use App\UseCases\Rack\Create\CreateRacks;
use App\UseCases\Rack\Create\CreateRacksInput;
use App\UseCases\Rack\QueryList\QueryListRack;
use App\UseCases\Rack\QueryList\QueryListRackInput;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
