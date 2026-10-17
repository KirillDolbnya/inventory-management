<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Addresses\AddressSuggestion;
use App\Exceptions\AddressSuggestionUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddressSuggestionRequest;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Http\JsonResponse;

class AddressSuggestionController extends Controller
{
    public function __construct(
        private readonly AddressSuggestion $addressSuggestion,
    ) {}

    /**
     * @throws AddressSuggestionUnavailableException
     */
    #[ScrambleResponse(
        status: 422,
        description: 'Validation error',
        type: 'array{message: string, errors: array<string, string[]>}'
    )]
    #[ScrambleResponse(
        status: 503,
        description: 'External address suggestion service is temporarily unavailable',
        type: 'array{message: string}'
    )]
    #[ScrambleResponse(
        status: 502,
        description: 'Invalid or corrupted response received from the external address suggestion service',
        type: 'array{message: string}'
    )]
    public function __invoke(AddressSuggestionRequest $request): JsonResponse
    {
        $validatedData = $request->validated();

        $address = $validatedData['address'];

        $addressSuggested = $this->addressSuggestion->getSuggestionAddress($address);

        $expectedAddresses = collect($addressSuggested->getSuggestedAddresses())->map(function ($address) {
            return [
                'address' => $address->getAddress(),
                'fias_id' => $address->getFiasId(),
            ];
        });

        return response()->json([
            'data' => $expectedAddresses,
        ]);
    }
}
