<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AddressSuggestionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'address' => ['required', 'string', 'min:2', 'max:300'],
        ];
    }

    public function messages(): array
    {
        return [
            'address.required' => 'Адрес обязателен для заполнения.',
            'address.string' => 'Адрес должен быть текстовой строкой.',
            'address.min' => 'Адрес должен содержать не менее 2 символов.',
            'address.max' => 'Адрес не должен превышать 300 символов.',
        ];
    }
}
