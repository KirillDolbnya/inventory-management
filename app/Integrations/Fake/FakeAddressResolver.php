<?php

namespace App\Integrations\Fake;

use App\Contracts\Addresses\AddressResolver;
use App\Data\Addresses\ResolvedAddress;
use App\Exceptions\FiasIdNotFoundException;

class FakeAddressResolver implements AddressResolver
{
    /**
     * @var array<string, array{address: string, latitude: float, longitude: float}>
     */
    private array $fiasIds = [
        '8ed1481e-1f9e-4340-9774-325db197bf5d' => [
            'address' => 'г. Москва, Ленинские горы, д. 1',
            'latitude' => 55.702936,
            'longitude' => 37.530768,
        ],
        'b8c9d0e1-f2a3-4b5c-8d9e-0f1a2b3c4d5e' => [
            'address' => 'г. Москва, ул. Петровка, д. 17',
            'latitude' => 55.7646600,
            'longitude' => 37.6162100,
        ],
    ];

    /**
     * @throws FiasIdNotFoundException
     */
    public function getAddressByFiasId(string $fiasId): ResolvedAddress
    {
        if (! array_key_exists($fiasId, $this->fiasIds)) {
            throw new FiasIdNotFoundException;
        }

        $data = $this->fiasIds[$fiasId];

        return new ResolvedAddress($data['address'], $data['latitude'], $data['longitude'], $fiasId);
    }
}
