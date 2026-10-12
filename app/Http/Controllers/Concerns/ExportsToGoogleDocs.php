<?php

namespace App\Http\Controllers\Concerns;

use App\Exceptions\GoogleDocsException;
use App\Models\Project;
use App\Models\ProjectGoogleDocument;
use App\Models\User;
use App\Services\GoogleDocsExportService;
use App\Services\GoogleOAuthService;
use Illuminate\Http\JsonResponse;

trait ExportsToGoogleDocs
{
    protected function googleDocsConnectPayload(GoogleOAuthService $oauth): JsonResponse
    {
        return response()->json([
            'success' => false,
            'needs_google_connect' => true,
            'connect_url' => $oauth->isConfigured()
                ? route('google.connect')
                : route('profile.edit'),
            'message' => $oauth->isConfigured()
                ? 'Подключите Google-аккаунт в профиле, чтобы сохранять документы в Google Docs.'
                : 'Google OAuth не настроен на сервере. Обратитесь к администратору.',
        ], 409);
    }

    /**
     * @param  array{source?: string, project_ai_message_id?: int|null}  $meta
     */
    protected function exportDocxPathToGoogleDocs(
        User $user,
        Project $project,
        string $tempFile,
        string $title,
        GoogleOAuthService $oauth,
        GoogleDocsExportService $exporter,
        array $meta = []
    ): JsonResponse {
        if (! $user->hasGoogleConnected()) {
            @unlink($tempFile);

            return $this->googleDocsConnectPayload($oauth);
        }

        $source = $meta['source'] ?? ProjectGoogleDocument::SOURCE_PROJECT_PLAN;
        $messageId = $meta['project_ai_message_id'] ?? null;
        $existing = $this->findExistingGoogleDocument($project, $source, $messageId);

        try {
            $document = $exporter->uploadDocxAsGoogleDoc(
                $user,
                $tempFile,
                $title,
                $existing?->google_file_id
            );

            $record = $this->persistGoogleDocument(
                $project,
                $user,
                $document,
                $source,
                $messageId,
                $existing
            );

            $updated = (bool) ($document['updated'] ?? false);

            return response()->json([
                'success' => true,
                'document' => [
                    'id' => $record->google_file_id,
                    'name' => $record->name,
                    'url' => $record->url,
                    'record_id' => $record->id,
                    'source' => $record->source,
                    'updated' => $updated,
                ],
                'message' => $updated
                    ? 'Документ обновлён в Google Docs.'
                    : 'Документ сохранён в Google Docs.',
            ]);
        } catch (GoogleDocsException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } finally {
            if (is_file($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    /**
     * @param  array{source?: string, project_ai_message_id?: int|null}  $meta
     */
    protected function exportXlsxPathToGoogleSheets(
        User $user,
        Project $project,
        string $tempFile,
        string $title,
        GoogleOAuthService $oauth,
        GoogleDocsExportService $exporter,
        array $meta = []
    ): JsonResponse {
        if (! $user->hasGoogleConnected()) {
            @unlink($tempFile);

            return $this->googleDocsConnectPayload($oauth);
        }

        $source = $meta['source'] ?? ProjectGoogleDocument::SOURCE_ESTIMATE;
        $existing = $this->findExistingGoogleDocument($project, $source);

        try {
            $document = $exporter->uploadXlsxAsGoogleSheet(
                $user,
                $tempFile,
                $title,
                $existing?->google_file_id
            );

            $record = $this->persistGoogleDocument(
                $project,
                $user,
                $document,
                $source,
                null,
                $existing
            );

            $updated = (bool) ($document['updated'] ?? false);

            return response()->json([
                'success' => true,
                'document' => [
                    'id' => $record->google_file_id,
                    'name' => $record->name,
                    'url' => $record->url,
                    'record_id' => $record->id,
                    'source' => $record->source,
                    'updated' => $updated,
                ],
                'message' => $updated
                    ? 'Таблица обновлена в Google Sheets.'
                    : 'Таблица сохранена в Google Sheets.',
            ]);
        } catch (GoogleDocsException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } finally {
            if (is_file($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    private function findExistingGoogleDocument(
        Project $project,
        string $source,
        ?int $projectAiMessageId = null
    ): ?ProjectGoogleDocument {
        $query = ProjectGoogleDocument::query()
            ->where('project_id', $project->id)
            ->where('source', $source);

        if (in_array($source, [
            ProjectGoogleDocument::SOURCE_MEETING,
            ProjectGoogleDocument::SOURCE_AUDIT_RECOMMENDATIONS,
        ], true)) {
            if ($projectAiMessageId === null) {
                return null;
            }

            $query->where('project_ai_message_id', $projectAiMessageId);
        } else {
            $query->whereNull('project_ai_message_id');
        }

        return $query->latest('id')->first();
    }

    /**
     * @param  array{id: string, name: string, url: string, updated?: bool}  $document
     */
    private function persistGoogleDocument(
        Project $project,
        User $user,
        array $document,
        string $source,
        ?int $projectAiMessageId,
        ?ProjectGoogleDocument $existing
    ): ProjectGoogleDocument {
        $attributes = [
            'user_id' => $user->id,
            'google_file_id' => $document['id'],
            'name' => $document['name'],
            'url' => $document['url'],
            'source' => $source,
            'project_ai_message_id' => $projectAiMessageId,
        ];

        if ($existing) {
            $existing->fill($attributes)->save();

            return $existing->fresh();
        }

        return ProjectGoogleDocument::query()->create([
            'project_id' => $project->id,
            ...$attributes,
        ]);
    }
}
