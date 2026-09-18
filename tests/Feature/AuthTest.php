<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('SignIn', function () {
    it('signs in a user', function () {
        $name = 'Name';
        $email = 'test@test.com';
        $password = 'password1234';

        User::factory()->createOne([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $response = $this->postJson(route('auth-sign-in'), [
            'email' => $email,
            'password' => $password,
            'remember' => false,
        ],
            [
                'Referer' => config('app.url'),
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'id',
            'name',
            'email',
        ]);
    });

    it('rejects invalid credentials', function () {
        $email = 'test@test.com';
        $password = 'password1234';

        $response = $this->postJson(route('auth-sign-in'), [
            'email' => $email,
            'password' => $password,
            'remember' => false,
        ],
            [
                'Referer' => config('app.url'),
            ]);

        $response->assertStatus(401);
        $response->assertJsonStructure([
            'status',
            'message',
        ]);
    });

    it('rejects invalid request data', function () {
        $email = 23;
        $password = 1;

        $response = $this->postJson(route('auth-sign-in'), [
            'email' => $email,
            'password' => $password,
            'remember' => false,
        ],
            [
                'Referer' => config('app.url'),
            ]);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'errors',
        ]);
    });
});

describe('Logout', function () {
    it('logs out an authenticated user', function () {
        $name = 'Name';
        $email = 'test@test.com';
        $password = 'password1234';

        User::factory()->createOne([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $this->postJson(route('auth-sign-in'), [
            'email' => $email,
            'password' => $password,
            'remember' => false,
        ],
            [
                'Referer' => config('app.url'),
            ]);

        $response = $this->postJson(route('auth-logout'), [], [
            'Referer' => config('app.url'),
        ]);

        $response->assertStatus(204);
    });

    it('rejects an unauthenticated logout request', function () {
        $response = $this->postJson(route('auth-logout'), [], [
            'Referer' => config('app.url'),
        ]);

        $response->assertStatus(401);
        $response->assertJsonStructure([
            'message',
        ]);
    });
});
