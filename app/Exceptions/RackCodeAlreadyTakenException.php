<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RackCodeAlreadyTakenException extends Exception
{
    /**
     * @var array<int, string>
     */
    private array $conflictCodes;

    /**
     * @param  array<int, string>  $codesConflicts
     */
    public function __construct(string $message = 'Один или несколько кодов стеллажей уже заняты на этом складе.', int $code = 422, array $codesConflicts = [])
    {
        parent::__construct($message, $code);
        $this->conflictCodes = $codesConflicts;
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [
                'code' => $this->conflictCodes,
            ],
        ], $this->getCode());
    }
}
