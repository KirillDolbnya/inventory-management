<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MissingWarehouseRacksException extends Exception
{
    private readonly string $errors;

    public function __construct(string $message = 'Переданы не все стеллажи склада', int $code = 422, ?string $errors = null)
    {
        $this->errors = $errors ?? $message;

        parent::__construct($message, $code);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [
                'racks' => [$this->errors],
            ],
        ], $this->getCode());
    }
}
