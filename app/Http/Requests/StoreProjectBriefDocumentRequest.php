<?php

namespace App\Http\Requests;

use App\Models\ProjectBriefDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectBriefDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:15360',
                'mimes:pdf,docx,xlsx,xls,txt,md,rtf,csv,jpg,jpeg,png,webp,gif',
            ],
            'kind' => ['nullable', 'string', Rule::in(ProjectBriefDocument::KINDS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Выберите файл.',
            'file.max' => 'Размер файла не должен превышать 15 МБ.',
            'file.mimes' => 'Поддерживаются PDF, DOCX, XLSX, TXT, MD, RTF, CSV и изображения JPG, PNG, WEBP, GIF.',
        ];
    }
}
