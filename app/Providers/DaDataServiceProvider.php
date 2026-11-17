<?php

namespace App\Providers;

use App\Contracts\Addresses\AddressResolver;
use App\Contracts\Addresses\AddressSuggestion;
use App\Integrations\DaDataAddressResolver;
use App\Integrations\DaDataAddressSuggestion;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class DaDataServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(AddressSuggestion::class, function () {
            $token = config('services.dadata.token');

            if (! is_string($token) || empty(trim($token))) {
                throw new RuntimeException('Отсутствует API KEY от DaData');
            }

            return new DaDataAddressSuggestion($token);
        });

        $this->app->bind(AddressResolver::class, function () {
            $token = config('services.dadata.token');

            if (! is_string($token) || empty(trim($token))) {
                throw new RuntimeException('Отсутствует API KEY от DaData');
            }

            return new DaDataAddressResolver($token);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
