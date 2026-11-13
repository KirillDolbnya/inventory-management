<?php

namespace App\Integrations;

use App\Contracts\Addresses\AddressResolver;
use App\Data\Addresses\ResolvedAddress;
use App\Exceptions\AddressResolverUnavailableException;
use App\Exceptions\FiasIdNotFoundException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class DaDataAddressResolver implements AddressResolver
{
    public function __construct(
        private readonly string $token
    ) {}

    /**
     * @throws AddressResolverUnavailableException
     * @throws FiasIdNotFoundException
     */
    public function getAddressByFiasId(string $fiasId): ResolvedAddress
    {
        try {
            $response = Http::acceptJson()->connectTimeout(2)->timeout(5)->withToken($this->token, 'Token')->post('https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/address', [
                'query' => $fiasId,
                'count' => 1,
            ]);

            $response->throw();
        } catch (ConnectionException|RequestException $exception) {
            throw new AddressResolverUnavailableException('Сервис поиска адресов временно недоступен. Попробуйте позже.', 503, $exception);
        }

        $expectedData = $response->json();

        if (! is_array($expectedData)) {
            throw new AddressResolverUnavailableException('Сервис поиска адресов временно недоступен. Попробуйте позже.', 502);
        }

        $validator = Validator::make($expectedData, [
            'suggestions' => ['present', 'list'],
            'suggestions.*' => ['present', 'array'],
            'suggestions.*.value' => ['required', 'string'],
            'suggestions.*.data' => ['required', 'array'],
            'suggestions.*.data.fias_id' => ['required', 'string'],
            'suggestions.*.data.geo_lat' => ['required', 'numeric', 'between:-90,90'],
            'suggestions.*.data.geo_lon' => ['required', 'numeric', 'between:-180,180'],
        ]);

        if ($validator->fails()) {
            throw new AddressResolverUnavailableException('Сервис поиска адресов временно недоступен. Попробуйте позже.', 502);
        }

        /** @var array{suggestions: array<int, array{value: string, data: array{fias_id: string, geo_lat: string|int|float, geo_lon: string|int|float}}>} $validated */
        $validated = $validator->validated();

        if (empty($validated['suggestions'])) {
            throw new FiasIdNotFoundException;
        }

        $resolverData = $validated['suggestions'][0];

        if (mb_strtolower($fiasId, 'UTF-8') !== mb_strtolower($resolverData['data']['fias_id'], 'UTF-8')) {
            throw new AddressResolverUnavailableException('Сервис поиска адресов временно недоступен. Попробуйте позже.', 502);
        }

        $latitude = floatval($resolverData['data']['geo_lat']);
        $longitude = floatval($resolverData['data']['geo_lon']);

        return new ResolvedAddress($resolverData['value'], $latitude, $longitude, $resolverData['data']['fias_id']);
    }
}
