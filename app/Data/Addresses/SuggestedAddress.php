<?php

namespace App\Data\Addresses;

readonly class SuggestedAddress
{
    public function __construct(
        private string $address,
        private string $fiasId
    ) {}

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getFiasId(): string
    {
        return $this->fiasId;
    }
}
