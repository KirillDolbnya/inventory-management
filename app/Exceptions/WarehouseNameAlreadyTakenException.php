<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseNameAlreadyTakenException extends Exception
{
    public function __construct(string $message = 'Название склада уже существует', int $code = 422)
    {
        parent::__construct($message, $code);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [
                'name' => [$this->getMessage()],
            ],
        ], $this->getCode());
    }
}
