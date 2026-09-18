<?php

namespace App\UseCases\Auth\SignIn;

readonly class SignInOutput
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
    ) {}
}
