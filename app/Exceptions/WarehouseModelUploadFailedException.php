<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseModelUploadFailedException extends Exception
{
    public function __construct(private readonly int $warehouseId)
    {
        parent::__construct('Не удалось сохранить файл, попробуйте еще раз.', 500);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], $this->getCode());
    }

    /**
     * @return array{warehouse_id: int, disk: string}
     */
    public function context(): array
    {
        return [
            'warehouse_id' => $this->warehouseId,
            'disk' => 'public',
        ];
    }
}
