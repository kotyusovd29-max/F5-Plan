<?php

namespace App\Http\Requests;

use App\Models\ProjectBriefDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendProjectAiMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $message = trim((string) $this->input('message', ''));

        $ids = $this->input('brief_document_ids');
        $hasDocuments = (is_array($ids) && count($ids) > 0) || $this->filled('brief_document_id');

        if ($message === '' && $hasDocuments) {
            $firstId = is_array($ids) && count($ids) > 0 ? $ids[0] : $this->input('brief_document_id');
            $document = ProjectBriefDocument::query()->find($firstId);
            $this->merge([
                'message' => $document?->isImage()
                    ? 'Проанализируй прикреплённые изображения и ответь по делу.'
                    : 'Проанализируй прикреплённые документы и ответь по делу.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $project = $this->route('project');

        return [
            'message' => ['required', 'string', 'min:2'],
            'thread' => ['nullable', 'string', Rule::in(['project', 'meeting', 'audit_recommendations', 'kp_generation'])],
            'mode' => ['nullable', 'string', Rule::in(['audit', 'benchmark_b2b', 'project_analysis', 'tz_plan', 'kp_work_stages', 'auto_tasks'])],
            'brief_document_id' => [
                'nullable',
                'integer',
                Rule::exists('project_brief_documents', 'id')->where(
                    fn ($query) => $query->where('project_id', $project?->id)
                ),
            ],
            'brief_document_ids' => ['nullable', 'array', 'max:5'],
            'brief_document_ids.*' => [
                'integer',
                Rule::exists('project_brief_documents', 'id')->where(
                    fn ($query) => $query->where('project_id', $project?->id)
                ),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.required' => 'Введите сообщение для ИИ или прикрепите файл/фото.',
            'message.min' => 'Сообщение слишком короткое.',
            'brief_document_id.exists' => 'Прикреплённый файл не найден в этом проекте.',
            'brief_document_ids.max' => 'Можно прикрепить не больше 5 файлов.',
            'brief_document_ids.*.exists' => 'Прикреплённый файл не найден в этом проекте.',
        ];
    }
}
