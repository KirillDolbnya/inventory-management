<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InvalidCredentialsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SignInRequest;
use App\UseCases\Auth\Logout\Logout;
use App\UseCases\Auth\SignIn\SignIn;
use App\UseCases\Auth\SignIn\SignInInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    /**
     * @throws InvalidCredentialsException
     */
    public function signIn(SignInRequest $request, SignIn $signIn): JsonResponse
    {
        $input = new SignInInput($request->string('email')->toString(), $request->string('password')->toString(), $request->boolean('remember'));

        $output = ($signIn)($input);

        $request->session()->regenerate();

        return response()->json(['id' => $output->id, 'name' => $output->name, 'email' => $output->email]);
    }

    public function logout(Request $request, Logout $logout): Response
    {
        ($logout)();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
