<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AddressResolverUnavailableException extends Exception
{
    public function __construct(string $message = 'Сервис поиска адресов временно недоступен. Попробуйте позже.', int $code = 503, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], $this->getCode());
    }
}
