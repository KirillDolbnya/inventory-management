<?php

namespace App\UseCases\Auth\SignIn;

readonly class SignInInput
{
    public function __construct(
        public string $email,
        public string $password,
        public bool $remember
    ) {}
}
