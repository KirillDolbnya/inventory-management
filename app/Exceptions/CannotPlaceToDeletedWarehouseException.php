<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CannotPlaceToDeletedWarehouseException extends Exception
{
    public function __construct(string $message = 'Невозможно разместить предмет, так как ячейка находится в архивированном складе.', int $code = 409)
    {
        parent::__construct($message, $code);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], $this->getCode());
    }
}
