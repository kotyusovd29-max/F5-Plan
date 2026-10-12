<?php

namespace App\Http\Controllers;

use App\Exceptions\AmoCrmException;
use App\Exceptions\AuditStreamAbortedException;
use App\Exceptions\F5AiException;
use App\Http\Controllers\Concerns\ExportsToGoogleDocs;
use App\Http\Requests\ApplyAmoCrmAiSeedRequest;
use App\Http\Requests\AuditAmoCrmAccountRequest;
use App\Http\Requests\ExportAmoCrmRequest;
use App\Http\Requests\ImportAmoCrmPipelinesRequest;
use App\Http\Requests\PreviewAmoCrmAiSeedRequest;
use App\Http\Requests\StoreProjectAmocrmAccountRequest;
use App\Models\ProjectGoogleDocument;
use App\Models\Project;
use App\Services\AmoCrmAccountService;
use App\Services\AmoCrmAccountAuditService;
use App\Services\AmoCrmAccountSnapshotService;
use App\Services\AmoCrmAiAuditDocxExporter;
use App\Services\AmoCrmAiSeedService;
use App\Services\AmoCrmExportService;
use App\Services\AmoCrmExportPreviewService;
use App\Services\AmoCrmPipelineImportService;
use App\Services\GoogleDocsExportService;
use App\Services\GoogleOAuthService;
use App\Support\LongRunningRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ProjectAmocrmController extends Controller
{
    use ExportsToGoogleDocs;

    public function connect(
        StoreProjectAmocrmAccountRequest $request,
        Project $project,
        AmoCrmAccountService $accounts
    ): RedirectResponse {
        try {
            $result = $accounts->connect(
                $project,
                $request->string('subdomain')->toString(),
                $request->string('access_token')->toString()
            );
        } catch (AmoCrmException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'Не удалось сохранить подключение amoCRM. Проверьте subdomain, токен и логи сервера.');
        }

        $summary = $result['snapshot_summary'] ?? [];
        $snapshotHint = '';

        if ($summary !== []) {
            $parts = [];

            if (! empty($summary['pipelines'])) {
                $parts[] = 'воронок: '.$summary['pipelines'];
            }

            if (! empty($summary['leads_total'])) {
                $parts[] = 'сделок выгружено: '.$summary['leads_total'];
            }

            if (! empty($summary['users'])) {
                $parts[] = 'пользователей: '.$summary['users'];
            }

            if (! empty($summary['widgets'])) {
                $parts[] = 'виджетов: '.$summary['widgets'];
            }

            if (! empty($summary['statuses_with_tips'])) {
                $parts[] = 'этапов с подсказками: '.$summary['statuses_with_tips'];
            }

            if ($parts !== []) {
                $snapshotHint = ' Снимок аккаунта: '.implode(', ', $parts).'.';
            }
        }

        return back()
            ->with(
                'success',
                'amoCRM подключён: '.$result['account_name'].' ('.$result['account']->subdomain.').'.$snapshotHint.' Выберите воронки для импорта.'
            )
            ->with('amocrm_import_open', true);
    }

    public function refreshSnapshot(
        Project $project,
        AmoCrmAccountSnapshotService $snapshots
    ): JsonResponse {
        LongRunningRequest::disableTimeouts();

        try {
            $snapshot = $snapshots->sync($project);
        } catch (AmoCrmException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Не удалось обновить снимок аккаунта amoCRM.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'summary' => $snapshot['summary'] ?? [],
            'snapshot_at' => $project->fresh()->amocrmAccount?->account_snapshot_at
                ?->timezone(config('app.timezone'))
                ?->format('d.m.Y H:i'),
            'message' => 'Снимок аккаунта amoCRM обновлён.',
        ]);
    }

    public function pipelines(Project $project, AmoCrmPipelineImportService $importer): JsonResponse
    {
        try {
            return response()->json([
                'pipelines' => $importer->listPipelines($project),
                'fields' => $importer->fieldsSummary($project),
            ]);
        } catch (AmoCrmException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Не удалось получить данные из amoCRM. Проверьте токен и попробуйте ещё раз.',
            ], 500);
        }
    }

    public function importPipelines(
        ImportAmoCrmPipelinesRequest $request,
        Project $project,
        AmoCrmPipelineImportService $importer
    ): JsonResponse {
        try {
            $stats = $importer->import(
                $project,
                $request->input('pipeline_ids', []),
                $request->boolean('include_unsorted'),
                $request->boolean('include_system'),
                $request->boolean('include_fields', true)
            );
        } catch (AmoCrmException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Не удалось импортировать данные из amoCRM. Проверьте логи сервера.',
            ], 500);
        }

        $parts = [];

        if ($stats['funnels_created'] > 0) {
            $parts[] = 'создано воронок: '.$stats['funnels_created'];
        }
        if ($stats['funnels_updated'] > 0) {
            $parts[] = 'обновлено воронок: '.$stats['funnels_updated'];
        }
        if ($stats['stages_created'] > 0) {
            $parts[] = 'создано этапов: '.$stats['stages_created'];
        }
        if ($stats['stages_updated'] > 0) {
            $parts[] = 'обновлено этапов: '.$stats['stages_updated'];
        }
        if (($stats['fields_created'] ?? 0) > 0) {
            $parts[] = 'создано полей: '.$stats['fields_created'];
        }
        if (($stats['fields_updated'] ?? 0) > 0) {
            $parts[] = 'обновлено полей: '.$stats['fields_updated'];
        }

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'message' => $parts === []
                ? 'Нечего импортировать — подходящих данных не найдено.'
                : 'Импорт завершён ('.implode(', ', $parts).').',
        ]);
    }

    public function disconnect(Project $project, AmoCrmAccountService $accounts): RedirectResponse
    {
        $accounts->disconnect($project);

        return back()->with('success', 'Аккаунт amoCRM отключён от проекта.');
    }

    public function preview(Project $project, AmoCrmExportPreviewService $previewer): JsonResponse
    {
        try {
            return response()->json($previewer->preview($project));
        } catch (AmoCrmException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Не удалось получить структуру amoCRM. Проверьте токен и попробуйте ещё раз.',
            ], 500);
        }
    }

    public function audit(
        AuditAmoCrmAccountRequest $request,
        Project $project,
        AmoCrmAccountAuditService $auditor
    ): JsonResponse {
        LongRunningRequest::disableTimeouts();

        try {
            return response()->json($auditor->audit(
                $project,
                $request->validated('pipeline_ids'),
                $request->boolean('include_fields', true),
                $request->user()?->id
            ));
        } catch (AmoCrmException|F5AiException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Не удалось выполнить AI-аудит amoCRM. Проверьте логи сервера.',
            ], 500);
        }
    }

    /**
     * AI-аудит со стримингом ответа (SSE): текст ИИ приходит в реальном времени,
     * без таймаута на крупных аккаунтах.
     */
    public function auditStream(
        AuditAmoCrmAccountRequest $request,
        Project $project,
        AmoCrmAccountAuditService $auditor
    ): StreamedResponse {
        LongRunningRequest::disableTimeouts();

        $pipelineIds = $request->validated('pipeline_ids');
        $includeFields = $request->boolean('include_fields', true);
        $userId = $request->user()?->id;

        return response()->stream(
            function () use ($project, $pipelineIds, $includeFields, $userId, $auditor) {
                LongRunningRequest::disableTimeouts();

                // Снимаем буферизацию PHP, чтобы события уходили в браузер сразу.
                // В тестах буферы PHPUnit не трогаем.
                if (! app()->runningUnitTests()) {
                    while (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                }

                $send = static function (string $event, array $data): void {
                    echo 'event: '.$event."\n";
                    echo 'data: '.json_encode($data, JSON_UNESCAPED_UNICODE)."\n\n";

                    if (ob_get_level() > 0) {
                        @ob_flush();
                    }

                    flush();
                };

                if (! app()->runningUnitTests()) {
                    // 2 КБ комментария, чтобы Apache/прокси сразу отдали поток, а не копировали ответ целиком.
                    echo ':'.str_repeat(' ', 2048)."\n\n";
                    flush();
                }

                try {
                    $result = $auditor->audit(
                        $project,
                        $pipelineIds,
                        $includeFields,
                        $userId,
                        static function (string $type, string $text) use ($send): void {
                            if (connection_aborted()) {
                                throw new AuditStreamAbortedException;
                            }

                            $send($type === 'chunk' ? 'chunk' : 'status', ['text' => $text]);
                        }
                    );

                    $send('done', $result);
                } catch (AuditStreamAbortedException) {
                    // Клиент прервал поток — частичный аудит не сохраняем.
                } catch (AmoCrmException|F5AiException $exception) {
                    $send('error', ['message' => $exception->getMessage()]);
                } catch (\Throwable $exception) {
                    report($exception);

                    $send('error', ['message' => 'Не удалось выполнить AI-аудит amoCRM. Проверьте логи сервера.']);
                }
            },
            200,
            [
                'Content-Type' => 'text/event-stream; charset=utf-8',
                'Cache-Control' => 'no-cache, no-store, no-transform',
                'X-Accel-Buffering' => 'no',
                'Connection' => 'keep-alive',
                'Content-Encoding' => 'none',
            ]
        );
    }

    public function exportAuditDocx(Project $project, AmoCrmAiAuditDocxExporter $exporter): BinaryFileResponse
    {
        $tempFile = $exporter->build($project);

        return response()
            ->download($tempFile, $exporter->filename($project))
            ->deleteFileAfterSend();
    }

    public function exportAuditGoogleDocs(
        Project $project,
        AmoCrmAiAuditDocxExporter $exporter,
        GoogleOAuthService $oauth,
        GoogleDocsExportService $googleDocs
    ): JsonResponse {
        $tempFile = $exporter->build($project);

        return $this->exportDocxPathToGoogleDocs(
            request()->user(),
            $project,
            $tempFile,
            $exporter->filename($project),
            $oauth,
            $googleDocs,
            ['source' => ProjectGoogleDocument::SOURCE_AMOCRM_AI_AUDIT]
        );
    }

    public function previewAiSeed(
        PreviewAmoCrmAiSeedRequest $request,
        Project $project,
        AmoCrmAiSeedService $seeder
    ): JsonResponse {
        try {
            @set_time_limit(300);

            $preview = $seeder->preview(
                $project,
                $request->string('prompt')->toString(),
                $request->user()?->id
            );
        } catch (AmoCrmException|F5AiException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Не удалось подготовить данные через ИИ. Попробуйте ещё раз.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            ...$preview,
        ]);
    }

    public function applyAiSeed(
        ApplyAmoCrmAiSeedRequest $request,
        Project $project,
        AmoCrmAiSeedService $seeder
    ): JsonResponse {
        try {
            @set_time_limit(180);

            $result = $seeder->apply($project, [
                'contacts' => $request->input('contacts', []),
                'leads' => $request->input('leads', []),
                'links' => $request->input('links', []),
            ]);
        } catch (AmoCrmException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Не удалось создать данные в amoCRM. Проверьте права токена и попробуйте ещё раз.',
            ], 500);
        }

        $parts = [];

        if (($result['contacts_created'] ?? 0) > 0) {
            $parts[] = 'контактов: '.$result['contacts_created'];
        }

        if (($result['leads_created'] ?? 0) > 0) {
            $parts[] = 'сделок: '.$result['leads_created'];
        }

        if (($result['links_created'] ?? 0) > 0) {
            $parts[] = 'связей: '.$result['links_created'];
        }

        $message = $parts === []
            ? 'Данные в amoCRM не созданы.'
            : 'В amoCRM создано: '.implode(', ', $parts).'.';

        if (($result['errors'] ?? []) !== []) {
            $message .= ' Часть запросов завершилась с ошибкой.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'result' => $result,
        ]);
    }

    public function export(
        ExportAmoCrmRequest $request,
        Project $project,
        AmoCrmExportService $exporter
    ): RedirectResponse {
        try {
            $result = $exporter->export($project, $request->exportOptions());
        } catch (AmoCrmException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'Выгрузка в amoCRM прервалась с внутренней ошибкой. Проверьте логи сервера.');
        }

        $created = ($result['pipelines_created'] ?? 0)
            + ($result['stages_created'] ?? 0)
            + ($result['tips_synced'] ?? 0)
            + ($result['fields_created'] ?? 0);
        $skipped = ($result['pipelines_skipped'] ?? 0) + ($result['fields_skipped'] ?? 0);
        $hasErrors = ($result['errors'] ?? []) !== [];

        $parts = [];

        if ($result['pipelines_created'] > 0) {
            $parts[] = 'воронок: '.$result['pipelines_created'];
        }
        if ($result['stages_created'] > 0) {
            $parts[] = 'этапов: '.$result['stages_created'];
        }
        if (($result['tips_synced'] ?? 0) > 0) {
            $parts[] = 'этапов обновлено: '.$result['tips_synced'];
        }
        if ($result['fields_created'] > 0) {
            $parts[] = 'полей: '.$result['fields_created'];
        }
        if ($skipped > 0) {
            $parts[] = 'пропущено (уже выгружено): '.$skipped;
        }

        if ($created === 0 && $skipped === 0 && $hasErrors) {
            return back()->with(
                'error',
                'Выгрузка не удалась: '.implode(' ', array_slice($result['errors'], 0, 3))
            );
        }

        $message = $parts === []
            ? 'Нечего выгружать: воронки и поля пусты или уже синхронизированы.'
            : 'Выгрузка в amoCRM завершена ('.implode(', ', $parts).').';

        if ($hasErrors) {
            $message .= ' Предупреждения: '.implode(' ', array_slice($result['errors'], 0, 3));
        }

        return back()->with('success', $message);
    }
}
