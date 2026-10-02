<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseNotFoundException extends Exception
{
    public function __construct(string $message = 'Склад с передаваемым id не найден', int $code = 404)
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
