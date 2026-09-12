<?php

namespace App\UseCases\Users;

use App\Exceptions\EmailAlreadyExistsException;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateUser
{
    public function __construct(
        private readonly UserRepository $userRepository,
    )
    {
    }

    public function __invoke(string $name, string $email, string $password): User
    {
        $validator = Validator::validate([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ],
        [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'min:8'],
        ],
        [
            'name.required' => 'Имя обязательно для заполнения.',
            'name.min' => 'Имя должено быть не менее 3 символов.',
            'name.max' => 'Имя не должно превышать 255 символов.',

            'email.required' => 'Электронная почта обязательна.',
            'email.email' => 'Введите корректный адрес почты.',

            'password.required' => 'Пароль не может быть пустым.',
            'password.min' => 'Пароль должен быть не менее 8 символов.',
        ]);

        if ($this->userRepository->existsByEmail($email)){
            throw ValidationException::withMessages([
                'email' => ['Пользователь с таким email уже существует'],
            ]);
        }

        $passwordHash = Hash::make($password);

        return $this->userRepository->create($name, $email, $passwordHash);
    }
}
