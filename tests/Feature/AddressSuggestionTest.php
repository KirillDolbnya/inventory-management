<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;

uses(RefreshDatabase::class);

describe('Address suggestions', function () {
    beforeEach(function () {
        $name = 'Name';
        $email = 'test@test.com';
        $password = 'password1234';

        $this->user = User::factory()->createOne([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        Http::preventStrayRequests();

        $this->token = 'test_dadata';
        config(['services.dadata.token' => $this->token]);
    });

    it('returns address suggestions', function () {
        Http::fake([
            'https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address' => Http::response([
                'suggestions' => [
                    [
                        'value' => 'г. Москва, Ленинские горы, д. 1',
                        'data' => [
                            'fias_id' => '8ed1481e-1f9e-4340-9774-325db197bf5d',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->be($this->user)->getJson(route('addresses-suggestions', [
            'address' => 'Москва Хабаровская',
        ]));

        $response->assertStatus(200);

        $response->assertJson([
            'data' => [
                [
                    'address' => 'г. Москва, Ленинские горы, д. 1',
                    'fias_id' => '8ed1481e-1f9e-4340-9774-325db197bf5d',
                ],
            ],
        ]);

        $response->assertJsonCount(1, 'data');

        Http::assertSent(function (Request $request) {
            $requestBody = $request->data();
            $authHeader = $request->header('Authorization')[0] ?? '';

            expect($request->method())->toBe('POST')
                ->and($request->url())->toBe('https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address')
                ->and($authHeader)->toBe('Token '.$this->token)
                ->and($requestBody['query'])->toBe('Москва Хабаровская')
                ->and($requestBody['count'])->toBe(5);

            return true;
        });
    });

    it('returns an empty list when no suggestions are found', function () {
        Http::fake([
            'https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address' => Http::response([
                'suggestions' => [],
            ], 200),
        ]);

        $response = $this->be($this->user)->getJson(route('addresses-suggestions', [
            'address' => 'Москва Хабаровская',
        ]));

        $response->assertStatus(200);

        $response->assertJson([
            'data' => [],
        ]);
    });

    it('returns 503 when DaData returns an unsuccessful response', function () {
        Http::fake([
            'https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address' => Http::response([
                'suggestions' => [],
            ], 500),
        ]);

        $response = $this->be($this->user)->getJson(route('addresses-suggestions', [
            'address' => 'Москва Хабаровская',
        ]));

        $response->assertStatus(503);

        $response->assertJson([
            'message' => 'Сервис подсказок адресов временно недоступен. Попробуйте позже.',
        ]);
    });

    it('returns 503 when the connection to DaData fails', function () {
        Http::fake([
            'https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address' => Http::failedConnection(),
        ]);

        $response = $this->be($this->user)->getJson(route('addresses-suggestions', [
            'address' => 'Москва Хабаровская',
        ]));

        $response->assertStatus(503);

        $response->assertJson([
            'message' => 'Сервис подсказок адресов временно недоступен. Попробуйте позже.',
        ]);
    });

    it('returns 502 when the external response structure is corrupted', function (array $fakeResponseBody) {
        Http::fake([
            'https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address' => Http::response($fakeResponseBody, 200),
        ]);

        $response = $this->be($this->user)->getJson(route('addresses-suggestions', [
            'address' => 'Москва Хабаровская',
        ]));

        $response->assertStatus(502);

        $response->assertJson([
            'message' => 'Сервис подсказок адресов временно недоступен. Попробуйте позже.',
        ]);
    })->with([
        'response_has_no_suggestions_field' => [
            'fakeResponseBody' => ['array' => []],
        ],
        'response_has_no_suggestions_type' => [
            'fakeResponseBody' => ['suggestions' => 'adsd'],
        ],
        'suggestions_items_are_not_arrays' => [
            'fakeResponseBody' => ['suggestions' => ['adsd']],
        ],
        'suggestion_has_no_value' => [
            'fakeResponseBody' => [
                'suggestions' => [
                    ['data' => ['fias_id' => '8ed1481e-1f9e-4340-9774-325db197bf5d']],
                ],
            ],
        ],
        'suggestion_value_is_not_a_string' => [
            'fakeResponseBody' => [
                'suggestions' => [
                    [
                        'value' => 12,
                        'data' => ['fias_id' => '8ed1481e-1f9e-4340-9774-325db197bf5d'],
                    ],
                ],
            ],
        ],
        'suggestion_data_is_not_an_array' => [
            'fakeResponseBody' => [
                'suggestions' => [
                    [
                        'value' => 'г. Москва, Ленинские горы, д. 1',
                        'data' => 'asd',
                    ],
                ],
            ],
        ],
        'suggestion_data_is_empty' => [
            'fakeResponseBody' => [
                'suggestions' => [
                    [
                        'value' => 'г. Москва, Ленинские горы, д. 1',
                        'data' => [],
                    ],
                ],
            ],
        ],
        'fias_id_is_empty' => [
            'fakeResponseBody' => [
                'suggestions' => [
                    [
                        'value' => 'г. Москва, Ленинские горы, д. 1',
                        'data' => ['fias_id' => ''],
                    ],
                ],
            ],
        ],
        'fias_id_is_not_a_string' => [
            'fakeResponseBody' => [
                'suggestions' => [
                    [
                        'value' => 'г. Москва, Ленинские горы, д. 1',
                        'data' => ['fias_id' => 123],
                    ],
                ],
            ],
        ],
    ]);

    it('rejects invalid data', function (array $payload, string $errorKey, string $expectedMessage) {
        Http::fake([
            'https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address' => Http::response([
                'suggestions' => [
                    [
                        'value' => 'г. Москва, Ленинские горы, д. 1',
                        'data' => [
                            'fias_id' => '8ed1481e-1f9e-4340-9774-325db197bf5d',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->be($this->user)->getJson(route('addresses-suggestions', $payload));

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                $errorKey => [$expectedMessage],
            ]);

        Http::assertNothingSent();
    })->with([
        'address_is_required' => [
            'payload' => [],
            'errorKey' => 'address',
            'expectedMessage' => 'Адрес обязателен для заполнения.',
        ],
        'address_must_be_a_string' => [
            'payload' => ['address' => ['битые_данные_в_виде_массива']],
            'errorKey' => 'address',
            'expectedMessage' => 'Адрес должен быть текстовой строкой.',
        ],
        'address_is_too_short' => [
            'payload' => ['address' => 'А'],
            'errorKey' => 'address',
            'expectedMessage' => 'Адрес должен содержать не менее 2 символов.',
        ],
        'address_is_too_long' => [
            'payload' => ['address' => str_repeat('A', 301)],
            'errorKey' => 'address',
            'expectedMessage' => 'Адрес не должен превышать 300 символов.',
        ],
    ]);

    it('rejects an unauthenticated request', function () {
        Http::fake([
            'https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address' => Http::response([
                'suggestions' => [
                    [
                        'value' => 'г. Москва, Ленинские горы, д. 1',
                        'data' => [
                            'fias_id' => '8ed1481e-1f9e-4340-9774-325db197bf5d',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->getJson(route('addresses-suggestions', [
            'address' => 'Москва Хабаровская',
        ]));

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);

        Http::assertNothingSent();
    });
});
