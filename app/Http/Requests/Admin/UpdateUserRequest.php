<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');
        $userId = $target->id;
        $isSelf = $this->user()->id === $userId;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => [$isSelf ? 'prohibited' : 'required', 'string', Rule::in(User::ROLES)],
            'rop_id' => [
                $isSelf ? 'prohibited' : Rule::requiredIf(fn () => $this->input('role') === User::ROLE_EMPLOYEE),
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('role', User::ROLE_ROP)
                    ->where('id', '!=', $userId)),
            ],
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
            'role.prohibited' => 'Нельзя изменить свою роль.',
            'role.required' => 'Выберите роль.',
            'role.in' => 'Некорректная роль.',
            'rop_id.required' => 'Выберите РОПа для сотрудника.',
            'rop_id.exists' => 'Выбранный пользователь не является РОПом.',
            'rop_id.prohibited' => 'Нельзя изменить свою роль.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var User $target */
            $target = $this->route('user');

            if ($this->user()->id === $target->id) {
                return;
            }

            $role = $this->input('role');

            if ($role === User::ROLE_EMPLOYEE && ! filled($this->input('rop_id'))) {
                $validator->errors()->add('rop_id', 'Выберите РОПа для сотрудника.');
            }

            if (
                $target->isRop()
                && $role !== User::ROLE_ROP
                && $target->employees()->exists()
            ) {
                $validator->errors()->add(
                    'role',
                    'Нельзя сменить роль РОПа, пока к нему привязаны сотрудники. Сначала переназначьте их.'
                );
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function userPayload(): array
    {
        /** @var User $target */
        $target = $this->route('user');
        $isSelf = $this->user()->id === $target->id;

        $data = $this->safe()->only(['name', 'email', 'password']);

        if ($isSelf) {
            return $data;
        }

        $roleData = $this->safe()->only(['role', 'rop_id']);

        return [
            ...$data,
            ...User::normalizeRolePayload($roleData),
        ];
    }
}
