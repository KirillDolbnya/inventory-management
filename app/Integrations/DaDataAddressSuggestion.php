<?php

namespace App\Integrations;

use App\Contracts\Addresses\AddressSuggestion;
use App\Data\Addresses\SuggestedAddress;
use App\Data\Addresses\SuggestedAddresses;
use App\Exceptions\AddressSuggestionUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class DaDataAddressSuggestion implements AddressSuggestion
{
    public function __construct(
        private readonly string $token
    ) {}

    /**
     * @throws AddressSuggestionUnavailableException
     */
    public function getSuggestionAddress(string $address): SuggestedAddresses
    {

        try {
            $response = Http::acceptJson()->connectTimeout(2)->timeout(5)->withToken($this->token, 'Token')->post('https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address', [
                'query' => $address,
                'count' => 5,
            ]);

            $response->throw();

        } catch (ConnectionException|RequestException $exception) {
            throw new AddressSuggestionUnavailableException('Сервис подсказок адресов временно недоступен. Попробуйте позже.', 503, $exception);
        }

        $expectedData = $response->json();

        if (! is_array($expectedData)) {
            throw new AddressSuggestionUnavailableException('Сервис подсказок адресов временно недоступен. Попробуйте позже.', 502);
        }

        $validator = Validator::make($expectedData, [
            'suggestions' => ['present', 'array'],
            'suggestions.*' => ['array'],
            'suggestions.*.value' => ['required', 'string'],
            'suggestions.*.data' => ['required', 'array'],
            'suggestions.*.data.fias_id' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            throw new AddressSuggestionUnavailableException('Сервис подсказок адресов временно недоступен. Попробуйте позже.', 502);
        }

        $validated = $validator->validated();

        /** @var array<int, array{value: string, data: array{fias_id: string}}> $suggestionsList */
        $suggestionsList = $validated['suggestions'];

        if (empty($suggestionsList)) {
            return new SuggestedAddresses([]);
        }

        $addressesData = collect($suggestionsList)
            ->map(function (array $item) {
                $address = $item['value'];
                $fiasId = $item['data']['fias_id'];

                return new SuggestedAddress($address, $fiasId);
            })
            ->values()
            ->all();

        return new SuggestedAddresses($addressesData);
    }
}
