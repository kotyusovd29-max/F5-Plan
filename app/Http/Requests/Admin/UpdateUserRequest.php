<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $userId = $this->route('user')->id;
        $isSelf = $this->user()->id === $userId;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'is_admin' => [$isSelf ? 'prohibited' : 'sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Укажите имя.',
            'email.required' => 'Укажите email.',
            'email.unique' => 'Пользователь с таким email уже существует.',
            'password.min' => 'Пароль должен быть не менее 8 символов.',
            'password.confirmed' => 'Пароли не совпадают.',
            'is_admin.prohibited' => 'Нельзя изменить свою роль.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->user()->isAdmin() || $this->user()->id === $this->route('user')->id) {
            return;
        }

        $this->merge([
            'is_admin' => $this->boolean('is_admin'),
        ]);
    }
}
