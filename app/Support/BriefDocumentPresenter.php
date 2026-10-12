<?php

namespace App\Support;

use App\Models\Project;
use App\Models\ProjectBriefDocument;

class BriefDocumentPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function forFrontend(ProjectBriefDocument $document, Project $project): array
    {
        return [
            'id' => $document->id,
            'kind' => $document->kind ?: ProjectBriefDocument::KIND_TZ,
            'original_name' => $document->original_name,
            'mime_type' => $document->mime_type,
            'is_image' => $document->isImage(),
            'size' => $document->size,
            'size_label' => self::formatSize((int) $document->size),
            'char_count' => $document->char_count,
            'created_at' => $document->created_at?->format('d.m.Y H:i'),
            'destroy_url' => route('projects.brief-documents.destroy', [$project, $document]),
            'generate_plan_url' => route('projects.brief-documents.generate-plan', [$project, $document]),
            'generate_work_stages_url' => route('projects.brief-documents.generate-work-stages', [$project, $document]),
        ];
    }

    public static function formatSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' Б';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' КБ';
        }

        return round($bytes / (1024 * 1024), 1).' МБ';
    }
}
