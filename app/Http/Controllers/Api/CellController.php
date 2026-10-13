<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\CellNotFoundException;
use App\Http\Controllers\Controller;
use App\UseCases\Inventory\QueryListByCell\QueryListByCellInventory;
use App\UseCases\Inventory\QueryListByCell\QueryListByCellInventoryInput;
use App\UseCases\Inventory\QueryListByCell\QueryListByCellInventoryItemOutput;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CellController extends Controller
{
    /**
     * @throws CellNotFoundException
     */
    #[ScrambleResponse(
        status: 404,
        description: 'Resource not found',
        type: 'array{message: string}'
    )]
    public function inventories(Request $request, QueryListByCellInventory $queryListByCellInventory): JsonResponse
    {
        $input = new QueryListByCellInventoryInput((int) $request->route('cellId'));

        $output = ($queryListByCellInventory)($input);

        $outputData = array_map(fn (QueryListByCellInventoryItemOutput $item) => [
            'id' => $item->id,
            'name' => $item->name,
            'description' => $item->description,
            'quantity' => $item->quantity,
            'quantity_in_cell' => $item->quantityInCell,
            'placement_id' => $item->placementId,
        ], $output->items);

        return response()->json([
            'data' => $outputData,
        ]);
    }
}
