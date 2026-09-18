<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\assertDatabaseCount;

uses(RefreshDatabase::class);

describe('The create:user command', function () {
    it('creates a user', function () {
        assertDatabaseCount('users', 0);

        $this->artisan('create:user')
            ->expectsQuestion('Введите имя', 'Kirill')
            ->expectsQuestion('Введите email', 'test@test.com')
            ->expectsQuestion('Введите пароль', 'password1234')
            ->expectsOutputToContain('Пользователь test@test.com создан.')
            ->assertExitCode(0);

        assertDatabaseCount('users', 1);
    });

    it('rejects a missing name', function () {
        $this->artisan('create:user')
            ->expectsQuestion('Введите имя', '')
            ->expectsQuestion('Введите email', 'test@test.com')
            ->expectsQuestion('Введите пароль', 'password1234')
            ->expectsOutputToContain('Имя обязательно для заполнения.')
            ->assertExitCode(1);

        assertDatabaseCount('users', 0);
    });

    it('rejects a name shorter than eight characters', function () {
        $this->artisan('create:user')
            ->expectsQuestion('Введите имя', 'Ra')
            ->expectsQuestion('Введите email', 'test@test.com')
            ->expectsQuestion('Введите пароль', 'password1234')
            ->expectsOutputToContain('Имя должено быть не менее 3 символов.')
            ->assertExitCode(1);

        assertDatabaseCount('users', 0);
    });

    it('rejects a name longer than 255 characters', function () {
        $longName = str_repeat('a', 256);

        $this->artisan('create:user')
            ->expectsQuestion('Введите имя', $longName)
            ->expectsQuestion('Введите email', 'test@test.com')
            ->expectsQuestion('Введите пароль', 'password1234')
            ->expectsOutputToContain('Имя не должно превышать 255 символов.')
            ->assertExitCode(1);

        assertDatabaseCount('users', 0);
    });

    it('rejects an invalid email address', function () {
        $this->artisan('create:user')
            ->expectsQuestion('Введите имя', 'Kirill')
            ->expectsQuestion('Введите email', 'test')
            ->expectsQuestion('Введите пароль', 'password1234')
            ->expectsOutputToContain('Введите корректный адрес почты.')
            ->assertExitCode(1);

        assertDatabaseCount('users', 0);
    });

    it('rejects a missing email address', function () {
        $this->artisan('create:user')
            ->expectsQuestion('Введите имя', 'Kirill')
            ->expectsQuestion('Введите email', '')
            ->expectsQuestion('Введите пароль', 'password1234')
            ->expectsOutputToContain('Электронная почта обязательна.')
            ->assertExitCode(1);

        assertDatabaseCount('users', 0);
    });

    it('rejects an email address that is already in use', function () {
        User::factory()->create([
            'name' => 'Kirill',
            'email' => 'test@test.com',
            'password' => 'password1234',
        ]);

        assertDatabaseCount('users', 1);

        $this->artisan('create:user')
            ->expectsQuestion('Введите имя', 'Kirill')
            ->expectsQuestion('Введите email', 'test@test.com')
            ->expectsQuestion('Введите пароль', 'password1234')
            ->expectsOutputToContain('Пользователь с таким email уже существует.')
            ->assertExitCode(1);

        assertDatabaseCount('users', 1);
    });

    it('rejects a password shorter than eight characters', function () {
        $this->artisan('create:user')
            ->expectsQuestion('Введите имя', 'Kirill')
            ->expectsQuestion('Введите email', 'test@test.com')
            ->expectsQuestion('Введите пароль', 'pass')
            ->expectsOutputToContain('Пароль должен быть не менее 8 символов.')
            ->assertExitCode(1);

        assertDatabaseCount('users', 0);
    });

    it('rejects a missing password', function () {
        $this->artisan('create:user')
            ->expectsQuestion('Введите имя', 'Kirill')
            ->expectsQuestion('Введите email', 'test@test.com')
            ->expectsQuestion('Введите пароль', '')
            ->expectsOutputToContain('Пароль не может быть пустым.')
            ->assertExitCode(1);

        assertDatabaseCount('users', 0);
    });
});
