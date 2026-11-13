<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryHasDependenciesException extends Exception
{
    public function __construct(string $message = 'Невозможно удалить предмет, так как он размещён в ячейках или зарезервирован под мероприятия.', int $code = 409)
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
