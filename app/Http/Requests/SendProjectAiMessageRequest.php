<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendProjectAiMessageRequest extends FormRequest
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
            'message' => ['required', 'string', 'min:2', 'max:50000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.required' => 'Введите сообщение для ИИ.',
            'message.min' => 'Сообщение слишком короткое.',
        ];
    }
}
