<?php

namespace App\Data\Addresses;

readonly class SuggestedAddresses
{
    /**
     * @param  list<SuggestedAddress>  $suggestedAddresses
     */
    public function __construct(
        private array $suggestedAddresses,
    ) {}

    /**
     * @return list<SuggestedAddress>
     */
    public function getSuggestedAddresses(): array
    {
        return $this->suggestedAddresses;
    }
}
