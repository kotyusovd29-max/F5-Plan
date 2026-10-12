<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', Rule::in(User::ROLES)],
            'rop_id' => [
                Rule::requiredIf(fn () => $this->input('role') === User::ROLE_EMPLOYEE),
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', User::ROLE_ROP)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Укажите имя.',
            'email.required' => 'Укажите email.',
            'email.unique' => 'Пользователь с таким email уже существует.',
            'password.required' => 'Укажите пароль.',
            'password.min' => 'Пароль должен быть не менее 8 символов.',
            'password.confirmed' => 'Пароли не совпадают.',
            'role.required' => 'Выберите роль.',
            'role.in' => 'Некорректная роль.',
            'rop_id.required' => 'Выберите РОПа для сотрудника.',
            'rop_id.exists' => 'Выбранный пользователь не является РОПом.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('role') !== User::ROLE_EMPLOYEE) {
                return;
            }

            if (! filled($this->input('rop_id'))) {
                $validator->errors()->add('rop_id', 'Выберите РОПа для сотрудника.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function userPayload(): array
    {
        $data = $this->safe()->only(['name', 'email', 'password', 'role', 'rop_id']);

        return [
            ...$data,
            ...User::normalizeRolePayload($data),
        ];
    }
}
