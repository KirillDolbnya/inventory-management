<?php

namespace App\Http\Requests;

use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Foundation\Http\FormRequest;

#[BodyParameter('email', format: 'email', required: true, type: 'string', description: 'Почта', example: 'test@test.test')]
#[BodyParameter('password', type: 'string', required: true, description: 'Пароль', example: '12345678')]
#[BodyParameter('remember', type: 'boolean', required: false, description: 'Запомнить сессию')]
class SignInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Пожалуйста, введите ваш email.',
            'email.email' => 'Введите корректный адрес электронной почты.',
            'email.string' => 'Email должен быть строкой.',

            'password.required' => 'Пожалуйста, введите пароль.',
            'password.string' => 'Пароль должен быть строкой.',

            'remember.boolean' => 'Значение должно быть логического типа (true/false).',
        ];
    }
}
