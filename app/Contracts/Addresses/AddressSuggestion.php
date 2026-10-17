<?php

namespace App\Contracts\Addresses;

use App\Data\Addresses\SuggestedAddresses;

interface AddressSuggestion
{
    public function getSuggestionAddress(string $address): SuggestedAddresses;
}
