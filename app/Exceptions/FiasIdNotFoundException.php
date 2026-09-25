<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FiasIdNotFoundException extends Exception
{
    public function __construct(string $message = 'Заданный ФИАС ID не существует', int $code = 422)
    {
        parent::__construct($message, $code);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [
                'fias_id' => [$this->getMessage()],
            ],
        ], $this->getCode());
    }
}
