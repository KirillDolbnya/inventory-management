<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryQuantityLessThanPlacedException extends Exception
{
    public function __construct(string $message = 'Общее количество товара не может быть меньше размещенного.', int $code = 409)
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
