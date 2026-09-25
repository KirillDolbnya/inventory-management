<?php

namespace App\Data\Addresses;

readonly class ResolvedAddress
{
    public function __construct(
        private string $address,
        private string $latitude,
        private string $longitude,
        private string $fiasId,
    ) {}

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getLatitude(): string
    {
        return $this->latitude;
    }

    public function getLongitude(): string
    {
        return $this->longitude;
    }

    public function getFiasId(): string
    {
        return $this->fiasId;
    }
}
