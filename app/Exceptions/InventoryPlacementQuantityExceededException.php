<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryPlacementQuantityExceededException extends Exception
{
    public function __construct(string $message = 'Невозможно разместить предмет в указанном количестве.', int $code = 422)
    {
        parent::__construct($message, $code);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [
                'quantity' => [$this->getMessage()],
            ],
        ], $this->getCode());
    }
}
