<?php

namespace App\UseCases\Auth\SignIn;

use App\Exceptions\InvalidCredentialsException;
use Illuminate\Support\Facades\Auth;

class SignIn
{
    /**
     * @throws InvalidCredentialsException
     */
    public function __invoke(SignInInput $input): SignInOutput
    {
        $isLogIn = Auth::guard('web')->attempt(['email' => $input->email, 'password' => $input->password], $input->remember);

        if (! $isLogIn) {
            throw new InvalidCredentialsException;
        }

        $user = Auth::user();

        return new SignInOutput($user->id, $user->name, $user->email);
    }
}
