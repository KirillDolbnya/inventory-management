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

describe('Get current user', function () {
    beforeEach(function () {
        $name = 'Name';
        $email = 'test@test.com';
        $this->password = 'password1234';

        $this->user = User::factory()->createOne([
            'name' => $name,
            'email' => $email,
            'password' => $this->password,
        ]);
    });

    it('returns an authenticated user', function () {
        $responseSignIn = $this->postJson(route('auth-sign-in'), [
            'email' => $this->user->email,
            'password' => $this->password,
            'remember' => true,
        ],
            [
                'Referer' => config('app.url'),
            ]);

        $responseSignIn->assertStatus(200);

        $responseGetCurrentUser = $this->getJson(route('auth-me'));

        $responseGetCurrentUser->assertStatus(200);

        $responseGetCurrentUser->assertJson([
            'id' => $this->user->id,
            'name' => $this->user->name,
            'email' => $this->user->email,
        ]);
    });

    it('rejects an unauthenticated request', function () {
        $responseGetCurrentUser = $this->getJson(route('auth-me'));

        $responseGetCurrentUser->assertStatus(401);

        $responseGetCurrentUser->assertJson([
            'message' => 'Unauthenticated.',
        ]);
    });

    it('rejects a request after logout', function () {
        $referer = config('app.url');

        $responseSignIn = $this->postJson(route('auth-sign-in'), [
            'email' => $this->user->email,
            'password' => $this->password,
            'remember' => true,
        ],
            [
                'Referer' => $referer,
            ]);

        $responseSignIn->assertStatus(200);

        auth()->forgetGuards();

        $responseGetCurrentUser = $this->getJson(route('auth-me'));

        $responseGetCurrentUser->assertStatus(200);

        $responseLogout = $this->postJson(route('auth-logout'), [], [
            'Referer' => $referer,
        ]);

        $responseLogout->assertStatus(204);
        auth()->forgetGuards();

        $responseGetCurrentUserMore = $this->getJson(route('auth-me'), [
            'Referer' => $referer,
        ]);

        $responseGetCurrentUserMore->assertStatus(401);

        $responseGetCurrentUserMore->assertJson([
            'message' => 'Unauthenticated.',
        ]);
    });
});
