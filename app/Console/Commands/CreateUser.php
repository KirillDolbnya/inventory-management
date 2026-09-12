<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Validation\ValidationException;
use App\UseCases\Users\CreateUser as CreateUserUseCase;

#[Signature('create:user')]
#[Description('Создать нового пользователя')]
class CreateUser extends Command implements Isolatable
{
    protected $signature = 'create:user';

    protected $description = 'Создать нового пользователя';

    /**
     * Execute the console command.
     */
    public function handle(CreateUserUseCase $createUser): int
    {
        $name = (string) ($this->ask('Введите имя') ?? '');
        $email = (string) ($this->ask('Введите email') ?? '');
        $password = (string) ($this->secret('Введите пароль') ?? '');

        try {
            $user = ($createUser)($name, $email, $password);

            $this->components->success("Пользователь {$user->email} создан.");

            return self::SUCCESS;
        }catch (ValidationException $exception){
            foreach ($exception->errors() as $messages){
                foreach ($messages as $message){
                    $this->components->error($message);
                }
            }

            return self::FAILURE;
        }
    }
}
