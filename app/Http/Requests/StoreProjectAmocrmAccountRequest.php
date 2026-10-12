<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectAmocrmAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subdomain' => ['required', 'string', 'max:100'],
            'access_token' => ['required', 'string', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subdomain.required' => 'Укажите subdomain аккаунта amoCRM.',
            'access_token.required' => 'Укажите access token.',
            'access_token.max' => 'Access token слишком длинный. Проверьте, что вставили только токен, без лишнего текста.',
        ];
    }
}
