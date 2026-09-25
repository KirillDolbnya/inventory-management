<?php

namespace App\Integrations\Fake;

use App\Contracts\Addresses\AddressResolver;
use App\Data\Addresses\ResolvedAddress;
use App\Exceptions\FiasIdNotFoundException;

class FakeAddressResolver implements AddressResolver
{
    /**
     * @throws FiasIdNotFoundException
     */
    public function getAddressByFiasId(string $fiasId): ResolvedAddress
    {
        if ($fiasId !== '8ed1481e-1f9e-4340-9774-325db197bf5d') {
            throw new FiasIdNotFoundException;
        }

        return new ResolvedAddress('г. Москва, Ленинские горы, д. 1', '55.702936', '37.530768', $fiasId);
    }
}
