<?php

use App\Contracts\Addresses\AddressResolver;
use App\Data\Addresses\ResolvedAddress;
use App\Exceptions\AddressResolverUnavailableException;
use App\Exceptions\FiasIdNotFoundException;
use Illuminate\Http\Client\Request;

describe('Address resolver', function () {
    beforeEach(function () {
        Http::preventStrayRequests();

        $this->token = 'test_dadata';
        config(['services.dadata.token' => $this->token]);

        $this->resolver = app(AddressResolver::class);
    });

    it('resolves an address by FIAS ID', function () {
        Http::fake([
            'https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/address' => Http::response([
                'suggestions' => [
                    [
                        'value' => 'г. Москва, Ленинские горы, д. 1',
                        'data' => [
                            'fias_id' => '8ed1481e-1f9e-4340-9774-325db197bf5d',
                            'geo_lat' => '55.702868',
                            'geo_lon' => '37.530865',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->resolver->getAddressByFiasId('8ed1481e-1f9e-4340-9774-325db197bf5d');

        expect($result)->toBeInstanceOf(ResolvedAddress::class)
            ->and($result->getAddress())->toBe('г. Москва, Ленинские горы, д. 1')
            ->and($result->getLongitude())->toBe(37.530865)
            ->and($result->getLatitude())->toBe(55.702868)
            ->and($result->getFiasId())->toBe('8ed1481e-1f9e-4340-9774-325db197bf5d');

        Http::assertSent(function (Request $request) {
            $requestBody = $request->data();
            $authHeader = $request->header('Authorization')[0] ?? '';

            expect($request->method())->toBe('POST')
                ->and($request->url())->toBe('https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/address')
                ->and($authHeader)->toBe('Token '.$this->token)
                ->and($requestBody['query'])->toBe('8ed1481e-1f9e-4340-9774-325db197bf5d')
                ->and($requestBody['count'])->toBe(1);

            return true;
        });
    });

    it('accepts a matching FIAS ID regardless of letter case', function () {
        Http::fake([
            'https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/address' => Http::response([
                'suggestions' => [
                    [
                        'value' => 'г. Москва, Ленинские горы, д. 1',
                        'data' => [
                            'fias_id' => '8ED1481E-1F9E-4340-9774-325DB197BF5D',
                            'geo_lat' => '55.702868',
                            'geo_lon' => '37.530865',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->resolver->getAddressByFiasId('8ed1481e-1f9e-4340-9774-325db197bf5d');

        expect($result)->toBeInstanceOf(ResolvedAddress::class)
            ->and($result->getAddress())->toBe('г. Москва, Ленинские горы, д. 1')
            ->and($result->getLongitude())->toBe(37.530865)
            ->and($result->getLatitude())->toBe(55.702868)
            ->and($result->getFiasId())->toBe('8ED1481E-1F9E-4340-9774-325DB197BF5D');

        Http::assertSent(function (Request $request) {
            $requestBody = $request->data();
            $authHeader = $request->header('Authorization')[0] ?? '';

            expect($request->method())->toBe('POST')
                ->and($request->url())->toBe('https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/address')
                ->and($authHeader)->toBe('Token '.$this->token)
                ->and($requestBody['query'])->toBe('8ed1481e-1f9e-4340-9774-325db197bf5d')
                ->and($requestBody['count'])->toBe(1);

            return true;
        });
    });

    it('rejects a response containing a different FIAS ID', function () {
        Http::fake([
            'https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/address' => Http::response([
                'suggestions' => [
                    [
                        'value' => 'г. Москва, Ленинские горы, д. 1',
                        'data' => [
                            'fias_id' => 'b8c9d0e1-f2a3-4b5c-8d9e-0f1a2b3c4d5e',
                            'geo_lat' => '55.702868',
                            'geo_lon' => '37.530865',
                        ],
                    ],
                ],
            ], 200),
        ]);

        expect(fn () => $this->resolver->getAddressByFiasId('8ed1481e-1f9e-4340-9774-325db197bf5d'))->toThrow(function (AddressResolverUnavailableException $exception) {
            expect($exception->getMessage())->toBe('Сервис поиска адресов временно недоступен. Попробуйте позже.')
                ->and($exception->getCode())->toBe(502);
        });
    });

    it('throws an unavailable exception when the connection to DaData fails', function () {
        Http::fake([
            'https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/address' => Http::failedConnection(),
        ]);

        expect(fn () => $this->resolver->getAddressByFiasId('8ed1481e-1f9e-4340-9774-325db197bf5d'))->toThrow(function (AddressResolverUnavailableException $exception) {
            expect($exception->getMessage())->toBe('Сервис поиска адресов временно недоступен. Попробуйте позже.')
                ->and($exception->getCode())->toBe(503);
        });
    });

    it('throws a not found exception when DaData returns no address', function () {
        Http::fake([
            'https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/address' => Http::response([
                'suggestions' => [],
            ], 200),
        ]);

        expect(fn () => $this->resolver->getAddressByFiasId('8ed1481e-1f9e-4340-9774-325db197bf5d'))->toThrow(
            function (FiasIdNotFoundException $exception) {
                expect($exception->getMessage())->toBe('Заданный ФИАС ID не существует')
                    ->and($exception->getCode())->toBe(422);
            }
        );
    });

    it('throws an unavailable exception when DaData returns an unsuccessful response', function () {
        Http::fake([
            'https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/address' => Http::response([
                'suggestions' => [],
            ], 500),
        ]);

        expect(fn () => $this->resolver->getAddressByFiasId('8ed1481e-1f9e-4340-9774-325db197bf5d'))->toThrow(function (AddressResolverUnavailableException $exception) {
            expect($exception->getMessage())->toBe('Сервис поиска адресов временно недоступен. Попробуйте позже.')
                ->and($exception->getCode())->toBe(503);
        });
    });

    it('rejects a non-array response from DaData', function (string $fakeResponseBody) {
        Http::fake([
            'https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/address' => Http::response($fakeResponseBody, 200),
        ]);

        expect(fn () => $this->resolver->getAddressByFiasId('8ed1481e-1f9e-4340-9774-325db197bf5d'))->toThrow(function (AddressResolverUnavailableException $exception) {
            expect($exception->getMessage())->toBe('Сервис поиска адресов временно недоступен. Попробуйте позже.')
                ->and($exception->getCode())->toBe(502);
        });
    })->with([
        'suggestions_field_is_missing' => [
            'fakeResponseBody' => 'asd',
        ],
    ]);

    it('rejects a malformed response from DaData', function (array $fakeResponseBody) {
        Http::fake([
            'https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/address' => Http::response($fakeResponseBody, 200),
        ]);

        expect(fn () => $this->resolver->getAddressByFiasId('8ed1481e-1f9e-4340-9774-325db197bf5d'))->toThrow(function (AddressResolverUnavailableException $exception) {
            expect($exception->getMessage())->toBe('Сервис поиска адресов временно недоступен. Попробуйте позже.')
                ->and($exception->getCode())->toBe(502);
        });
    })->with([
        'suggestions_field_is_missing' => [
            'fakeResponseBody' => ['array' => []],
        ],
        'suggestions_field_is_not_a_list' => [
            'fakeResponseBody' => [
                'suggestions' => [
                    'some_key' => [
                        'value' => 'г. Москва, Ленинские горы, д. 1',
                        'data' => ['fias_id' => '8ed1481e-1f9e-4340-9774-325db197bf5d', 'geo_lat' => '55.702868', 'geo_lon' => '37.530865'],
                    ],
                ],
            ],
        ],
        'suggestions_item_is_not_an_array' => [
            'fakeResponseBody' => ['suggestions' => ['string_instead_of_array']],
        ],
        'suggestion_value_is_missing_or_empty' => [
            'fakeResponseBody' => [
                'suggestions' => [
                    [
                        'value' => '',
                        'data' => ['fias_id' => '8ed1481e-1f9e-4340-9774-325db197bf5d', 'geo_lat' => '55.702868', 'geo_lon' => '37.530865'],
                    ],
                ],
            ],
        ],
        'suggestion_value_is_not_a_string' => [
            'fakeResponseBody' => [
                'suggestions' => [
                    [
                        'value' => 12345,
                        'data' => ['fias_id' => '8ed1481e-1f9e-4340-9774-325db197bf5d', 'geo_lat' => '55.702868', 'geo_lon' => '37.530865'],
                    ],
                ],
            ],
        ],
        'suggestion_data_is_missing_or_not_an_array' => [
            'fakeResponseBody' => [
                'suggestions' => [
                    [
                        'value' => 'г. Москва, Ленинские горы, д. 1',
                        'data' => 'not_an_array',
                    ],
                ],
            ],
        ],
        'fias_id_is_missing_or_empty' => [
            'fakeResponseBody' => [
                'suggestions' => [
                    [
                        'value' => 'г. Москва, Ленинские горы, д. 1',
                        'data' => ['fias_id' => '', 'geo_lat' => '55.702868', 'geo_lon' => '37.530865'],
                    ],
                ],
            ],
        ],
        'fias_id_is_not_a_string' => [
            'fakeResponseBody' => [
                'suggestions' => [
                    [
                        'value' => 'г. Москва, Ленинские горы, д. 1',
                        'data' => ['fias_id' => 999, 'geo_lat' => '55.702868', 'geo_lon' => '37.530865'],
                    ],
                ],
            ],
        ],
        'geo_lat_is_not_numeric' => [
            'fakeResponseBody' => [
                'suggestions' => [
                    [
                        'value' => 'г. Москва, Ленинские горы, д. 1',
                        'data' => ['fias_id' => '8ed1481e-1f9e-4340-9774-325db197bf5d', 'geo_lat' => 'abc', 'geo_lon' => '37.530865'],
                    ],
                ],
            ],
        ],
        'geo_lat_is_out_of_between_range' => [
            'fakeResponseBody' => [
                'suggestions' => [
                    [
                        'value' => 'г. Москва, Ленинские горы, д. 1',
                        'data' => ['fias_id' => '8ed1481e-1f9e-4340-9774-325db197bf5d', 'geo_lat' => '95.5', 'geo_lon' => '37.530865'],
                    ],
                ],
            ],
        ],
        'geo_lon_is_not_numeric' => [
            'fakeResponseBody' => [
                'suggestions' => [
                    [
                        'value' => 'г. Москва, Ленинские горы, д. 1',
                        'data' => ['fias_id' => '8ed1481e-1f9e-4340-9774-325db197bf5d', 'geo_lat' => '55.702868', 'geo_lon' => 'xyz'],
                    ],
                ],
            ],
        ],
        'geo_lon_is_out_of_between_range' => [
            'fakeResponseBody' => [
                'suggestions' => [
                    [
                        'value' => 'г. Москва, Ленинские горы, д. 1',
                        'data' => ['fias_id' => '8ed1481e-1f9e-4340-9774-325db197bf5d', 'geo_lat' => '55.702868', 'geo_lon' => '-185.0'],
                    ],
                ],
            ],
        ],
    ]);
});
