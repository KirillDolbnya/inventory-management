<?php

namespace App\UseCases\Auth\Logout;

use Illuminate\Support\Facades\Auth;

class Logout
{
    public function __invoke(): void
    {
        Auth::guard('web')->logout();
    }
}
