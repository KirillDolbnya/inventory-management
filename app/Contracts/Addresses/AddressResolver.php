<?php

namespace App\Contracts\Addresses;

use App\Data\Addresses\ResolvedAddress;
use App\Exceptions\AddressResolverUnavailableException;
use App\Exceptions\FiasIdNotFoundException;

interface AddressResolver
{
    /**
     * @throws AddressResolverUnavailableException
     * @throws FiasIdNotFoundException
     */
    public function getAddressByFiasId(string $fiasId): ResolvedAddress;
}
