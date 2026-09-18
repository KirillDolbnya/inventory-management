<?php

namespace App\Repositories;

use App\Models\User;

class UserRepository
{
    public function create(string $name, string $email, string $password): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);
    }

    public function existsByEmail(string $email): bool
    {
        return User::query()->where('email', $email)->exists();
    }
}
