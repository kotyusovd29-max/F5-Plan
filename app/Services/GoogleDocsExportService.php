<?php

namespace App\Services;

use App\Exceptions\GoogleDocsException;
use App\Models\User;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Exception as GoogleServiceException;

class GoogleDocsExportService
{
    public function __construct(
        private readonly GoogleOAuthService $oauth
    ) {}

    /**
     * @return array{id: string, name: string, url: string, updated: bool}
     */
    public function uploadDocxAsGoogleDoc(
        User $user,
        string $docxPath,
        string $title,
        ?string $existingFileId = null
    ): array {
        if (! is_file($docxPath)) {
            throw new GoogleDocsException('Файл DOCX для выгрузки не найден.');
        }

        $client = $this->oauth->clientForUser($user);
        $drive = new Drive($client);
        $name = $this->normalizeTitle($title);
        $contents = file_get_contents($docxPath);

        if ($contents === false || $contents === '') {
            throw new GoogleDocsException('Файл DOCX пуст или не читается.');
        }

        $docxMime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        $docUrl = 'https://docs.google.com/document/d/%s/edit';

        if ($existingFileId) {
            try {
                return $this->updateDriveFile(
                    $drive,
                    $existingFileId,
                    $name,
                    $contents,
                    $docxMime,
                    $docUrl,
                    'документ'
                );
            } catch (GoogleDocsException $exception) {
                if (! $this->isMissingFileException($exception)) {
                    // Битый/чужой file id — создаём новый документ.
                    if (! $this->isBadRequestException($exception)) {
                        throw $exception;
                    }
                }
            }
        }

        try {
            return $this->createDriveFile(
                $drive,
                $name,
                'application/vnd.google-apps.document',
                $contents,
                $docxMime,
                $docUrl,
                'документ'
            );
        } catch (GoogleDocsException $exception) {
            // Конвертация в Google Doc иногда даёт 400 на OOXML — сохраняем как обычный DOCX.
            if (! $this->isBadRequestException($exception)) {
                throw $exception;
            }

            return $this->createDriveFile(
                $drive,
                $name.'.docx',
                $docxMime,
                $contents,
                $docxMime,
                'https://drive.google.com/file/d/%s/view',
                'документ'
            );
        }
    }

    /**
     * @return array{id: string, name: string, url: string, updated: bool}
     */
    public function uploadXlsxAsGoogleSheet(
        User $user,
        string $xlsxPath,
        string $title,
        ?string $existingFileId = null
    ): array {
        if (! is_file($xlsxPath)) {
            throw new GoogleDocsException('Файл XLSX для выгрузки не найден.');
        }

        $client = $this->oauth->clientForUser($user);
        $drive = new Drive($client);
        $name = $this->normalizeSpreadsheetTitle($title);
        $contents = file_get_contents($xlsxPath);

        if ($existingFileId) {
            try {
                return $this->updateDriveFile(
                    $drive,
                    $existingFileId,
                    $name,
                    $contents,
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'https://docs.google.com/spreadsheets/d/%s/edit',
                    'таблицу'
                );
            } catch (GoogleDocsException $exception) {
                if (! $this->isMissingFileException($exception)) {
                    throw $exception;
                }
            }
        }

        return $this->createDriveFile(
            $drive,
            $name,
            'application/vnd.google-apps.spreadsheet',
            $contents,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'https://docs.google.com/spreadsheets/d/%s/edit',
            'таблицу'
        );
    }

    /**
     * @return array{id: string, name: string, url: string, updated: bool}
     */
    private function createDriveFile(
        Drive $drive,
        string $name,
        string $googleMimeType,
        string $contents,
        string $uploadMimeType,
        string $urlTemplate,
        string $entityLabel
    ): array {
        $meta = new DriveFile([
            'name' => $name,
            'mimeType' => $googleMimeType,
        ]);

        try {
            $created = $drive->files->create($meta, [
                'data' => $contents,
                'mimeType' => $uploadMimeType,
                'uploadType' => 'multipart',
                'fields' => 'id,name,webViewLink',
            ]);
        } catch (GoogleServiceException $exception) {
            throw $this->mapDriveException($exception, $entityLabel);
        }

        $id = (string) $created->getId();
        $fileName = (string) ($created->getName() ?: $name);
        $url = (string) ($created->getWebViewLink() ?: sprintf($urlTemplate, $id));

        return [
            'id' => $id,
            'name' => $fileName,
            'url' => $url,
            'updated' => false,
        ];
    }

    /**
     * @return array{id: string, name: string, url: string, updated: bool}
     */
    private function updateDriveFile(
        Drive $drive,
        string $fileId,
        string $name,
        string $contents,
        string $uploadMimeType,
        string $urlTemplate,
        string $entityLabel
    ): array {
        $meta = new DriveFile([
            'name' => $name,
        ]);

        try {
            $updated = $drive->files->update($fileId, $meta, [
                'data' => $contents,
                'mimeType' => $uploadMimeType,
                'uploadType' => 'multipart',
                'fields' => 'id,name,webViewLink',
            ]);
        } catch (GoogleServiceException $exception) {
            throw $this->mapDriveException($exception, $entityLabel, $fileId);
        }

        $id = (string) ($updated->getId() ?: $fileId);
        $fileName = (string) ($updated->getName() ?: $name);
        $url = (string) ($updated->getWebViewLink() ?: sprintf($urlTemplate, $id));

        return [
            'id' => $id,
            'name' => $fileName,
            'url' => $url,
            'updated' => true,
        ];
    }

    private function mapDriveException(
        GoogleServiceException $exception,
        string $entityLabel,
        ?string $fileId = null
    ): GoogleDocsException {
        $message = $exception->getMessage();
        $code = (int) $exception->getCode();
        $errors = $exception->getErrors();

        if (is_array($errors) && $errors !== []) {
            $reasons = collect($errors)
                ->map(fn ($row) => is_array($row) ? ($row['reason'] ?? $row['message'] ?? null) : null)
                ->filter()
                ->unique()
                ->implode(', ');

            if ($reasons !== '') {
                $message .= ' ['.$reasons.']';
            }
        }

        if (
            $fileId
            && (
                $code === 404
                || str_contains($message, 'File not found')
                || str_contains($message, 'notFound')
            )
        ) {
            return new GoogleDocsException(
                'Google-файл не найден: '.$fileId,
                previous: $exception
            );
        }

        if (
            str_contains($message, 'insufficient')
            || str_contains($message, 'ACCESS_TOKEN_SCOPE_INSUFFICIENT')
            || str_contains($message, 'PERMISSION_DENIED')
        ) {
            return new GoogleDocsException(
                'Недостаточно прав Google Drive. В личном кабинете нажмите «Отключить», затем «Подключить Google» снова (нужен доступ к Drive). Также в Google Cloud Console включите API «Google Drive API».',
                previous: $exception
            );
        }

        $action = $fileId ? 'обновить' : 'сохранить';

        return new GoogleDocsException(
            "Не удалось {$action} {$entityLabel} в Google Drive: ".$message,
            previous: $exception
        );
    }

    private function isMissingFileException(GoogleDocsException $exception): bool
    {
        return str_starts_with($exception->getMessage(), 'Google-файл не найден:');
    }

    private function isBadRequestException(GoogleDocsException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, '"code": 400')
            || str_contains($message, 'Bad Request')
            || str_contains($message, 'badRequest');
    }

    private function normalizeTitle(string $title): string
    {
        $title = trim(preg_replace('/\.docx$/i', '', $title) ?? $title);
        $title = trim((string) preg_replace('/[\\\\\/:*?"<>|]+/u', ' ', $title));
        $title = trim((string) preg_replace('/\s+/u', ' ', $title));
        // Google иногда капризничает на очень длинных именах.
        if (mb_strlen($title) > 180) {
            $title = mb_substr($title, 0, 180);
        }

        return $title !== '' ? $title : 'Документ F5 Plan';
    }

    private function normalizeSpreadsheetTitle(string $title): string
    {
        $title = trim(preg_replace('/\.xlsx$/i', '', $title) ?? $title);
        $title = trim($title);

        return $title !== '' ? $title : 'Смета F5 Plan';
    }
}
