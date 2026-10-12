<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->user()->id),
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'current_password' => ['required_with:password', 'current_password'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Укажите имя.',
            'email.required' => 'Укажите email.',
            'email.unique' => 'Этот email уже занят.',
            'password.min' => 'Пароль должен быть не менее 8 символов.',
            'password.confirmed' => 'Пароли не совпадают.',
            'current_password.required_with' => 'Введите текущий пароль для смены пароля.',
            'current_password.current_password' => 'Текущий пароль указан неверно.',
        ];
    }
}
