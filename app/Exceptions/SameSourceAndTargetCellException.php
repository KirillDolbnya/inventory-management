<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SameSourceAndTargetCellException extends Exception
{
    public function __construct(string $message = 'Исходная и целевая ячейки совпадают. Перемещение предмета внутри одной и той же ячейки невозможно.', int $code = 422)
    {
        parent::__construct($message, $code);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [
                'target_cell_id' => [$this->getMessage()],
            ],
        ], $this->getCode());
    }
}
