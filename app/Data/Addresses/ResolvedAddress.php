<?php

namespace App\Data\Addresses;

readonly class ResolvedAddress
{
    public function __construct(
        private string $address,
        private float $latitude,
        private float $longitude,
        private string $fiasId,
    ) {}

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    public function getFiasId(): string
    {
        return $this->fiasId;
    }
}
