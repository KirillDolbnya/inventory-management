<?php

namespace App\Contracts\Addresses;

use App\Data\Addresses\ResolvedAddress;
use App\Exceptions\FiasIdNotFoundException;

interface AddressResolver
{
    /**
     * @throws FiasIdNotFoundException
     */
    public function getAddressByFiasId(string $fiasId): ResolvedAddress;
}
