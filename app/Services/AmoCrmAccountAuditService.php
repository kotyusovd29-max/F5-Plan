<?php

namespace App\Services;

use App\Exceptions\AmoCrmException;
use App\Models\Project;
use App\Models\ProjectAiMessage;
use App\Models\ProjectAmocrmAccount;
use App\Support\AmoCrmMapper;
use App\Support\F5AiModels;
use App\Support\F5WidgetCatalog;
use App\Support\LongRunningRequest;

class AmoCrmAccountAuditService
{
    private const COMPLETION_MARKER = '[КОНЕЦ АУДИТА]';

    public function __construct(
        private readonly F5AiService $f5AiService,
        private readonly AmoCrmAccountSnapshotService $snapshots
    ) {}

    /**
     * @param  list<int>  $pipelineIds
     * @param  callable(string $type, string $text): void|null  $onEvent
     *         События прогресса: status — этап подготовки, chunk — фрагмент ответа ИИ.
     * @return array{content: string, audited_at: string}
     */
    public function audit(
        Project $project,
        array $pipelineIds = [],
        bool $includeFields = true,
        ?int $userId = null,
        ?callable $onEvent = null
    ): array {
        $account = $project->amocrmAccount;

        if (! $account instanceof ProjectAmocrmAccount) {
            throw new AmoCrmException('Сначала подключите аккаунт amoCRM к проекту.');
        }

        $pipelineIds = array_values(array_unique(array_map('intval', $pipelineIds)));
        $pipelineIds = array_values(array_filter($pipelineIds, fn (int $id) => $id > 0));

        if ($pipelineIds === []) {
            throw new AmoCrmException('Выберите хотя бы одну воронку для аудита.');
        }

        $client = new AmoCrmClient($account);
        $project->load(['funnels.stages', 'skkFields']);

        LongRunningRequest::disableTimeouts();

        $emit = $onEvent ?? static fn () => null;

        // Свежая полная выгрузка переиспользуется; иначе собирается заново в БД проекта.
        $export = [];
        try {
            $emit('status', 'Проверяю выгрузку данных amoCRM…');
            $export = $this->snapshots->syncIfStale(
                $project,
                20,
                $onEvent === null ? null : function (array $progress) use ($emit) {
                    $emit('status', match ($progress['stage'] ?? '') {
                        'leads' => 'Выгружаю сделки из amoCRM: '.$progress['loaded'].'…',
                        'tasks' => 'Выгружаю открытые задачи: '.$progress['loaded'].'…',
                        default => 'Собираю данные аккаунта amoCRM…',
                    });
                }
            );
            $account->refresh();
        } catch (\Throwable $exception) {
            report($exception);
        }

        $allPipelines = $client->getPipelines();
        $selectedPipelines = collect($allPipelines)
            ->filter(fn ($pipeline) => is_array($pipeline) && in_array((int) ($pipeline['id'] ?? 0), $pipelineIds, true))
            ->values()
            ->all();

        if ($selectedPipelines === []) {
            throw new AmoCrmException('Выбранные воронки не найдены в amoCRM.');
        }

        $accountSnapshot = $export !== []
            ? $export
            : (is_array($account->account_snapshot) ? $account->account_snapshot : []);
        $snapshotContext = $this->snapshots->auditContext($accountSnapshot);
        $selectedIds = array_map('intval', $pipelineIds);

        if (isset($snapshotContext['pipelines']) && is_array($snapshotContext['pipelines'])) {
            $snapshotContext['pipelines'] = collect($snapshotContext['pipelines'])
                ->filter(fn ($pipeline) => in_array((int) ($pipeline['id'] ?? 0), $selectedIds, true))
                ->values()
                ->all();
        }

        if (isset($snapshotContext['digital_pipeline']['pipelines']) && is_array($snapshotContext['digital_pipeline']['pipelines'])) {
            $snapshotContext['digital_pipeline']['pipelines'] = collect($snapshotContext['digital_pipeline']['pipelines'])
                ->filter(fn ($pipeline) => in_array((int) ($pipeline['pipeline_id'] ?? 0), $selectedIds, true))
                ->values()
                ->all();
        }

        $context = [
            'project' => [
                'company_name' => $project->company_name,
                'website' => $project->website,
                'business_sphere' => $project->business_sphere,
                'employees_count' => $project->employees_count,
                'client_tasks' => $project->client_tasks,
                'current_services' => $project->current_services,
            ],
            'amo_account' => [
                'name' => $account->account_name,
                'subdomain' => $account->subdomain,
                'pipelines_total_in_account' => count($allPipelines),
                'pipelines_selected_count' => count($selectedPipelines),
                'pipelines' => $this->pipelineContext($selectedPipelines),
                'custom_fields' => $includeFields
                    ? $this->customFieldsContext($client)
                    : new \stdClass,
                'custom_fields_included' => $includeFields,
            ],
            'amo_account_live_snapshot' => $snapshotContext,
            'planned_structure' => [
                'funnels' => $project->funnels->map(fn ($funnel) => [
                    'name' => $funnel->name,
                    'amocrm_id' => $funnel->amocrm_id,
                    'stages' => $funnel->stages->pluck('name')->values()->all(),
                ])->values()->all(),
                'skk_fields_count' => $project->skkFields->count(),
                'skk_fields_by_entity' => $project->skkFields
                    ->groupBy('entity_type')
                    ->map(fn ($fields) => $fields->pluck('name')->take(40)->values()->all())
                    ->all(),
            ],
            'f5_widgets_catalog' => F5WidgetCatalog::forAiContext(),
        ];

        $messages = [
            [
                'role' => 'system',
                'content' => implode("\n", [
                    'Ты эксперт F5 по аудиту и автоматизации amoCRM.',
                    'Проанализируй структуру аккаунта клиента: выбранные воронки, этапы, поля, полную выгрузку live-аккаунта (пользователи, виджеты, вебхуки, источники, причины отказа, аналитика всех сделок, открытые задачи, события, подсказки этапов, Digital Pipeline если доступен).',
                    'Особое внимание удели автоматизациям на этапах: descriptions/подсказки, триггеры digital_pipeline (если есть), вебхуки, установленные виджеты, открытые задачи и события смены статусов.',
                    'Используй leads_analytics (посчитана по полной выгрузке всех сделок аккаунта) для оценки загрузки этапов, конверсии и узких мест воронки; не выдумывай объёмы сделок сверх переданных данных.',
                    'Анализируй только переданные данные — не додумывай структуру остального аккаунта.',
                    'Дай прикладные рекомендации: что улучшить, что автоматизировать, где есть риски и быстрые победы.',
                    'Обязательно предложи автоматизации через реальные виджеты команды F5 из f5_widgets_catalog.',
                    'Для каждого рекомендованного виджета укажи точное название/slug, сценарий использования, ожидаемый эффект и в какой воронке/этапе он нужен.',
                    'Если виджет из каталога не подходит — так и напиши, не выдумывай несуществующие виджеты.',
                    'Структура ответа: разделы аудита по данным аккаунта, затем рекомендации по настройке/автоматизации, а ПОСЛЕДНИМ разделом обязательно сделай:',
                    '## Советы по улучшению продаж',
                    'В этом последнем разделе дай идеи, как усилить продажи у клиента: улучшения процессов в amoCRM и/или разработка новых функций под сферу бизнеса и особенности аккаунта (например, кастомные виджеты под отрасль и сценарии клиента, доработки воронок, отчётности, коммуникаций).',
                    'Опирайся на business_sphere, client_tasks, текущие воронки/поля/виджеты и узкие места из снимка; отделяй готовые виджеты F5 от идей кастомной разработки.',
                    'Пиши по-русски, структурно, без воды. Не выдумывай данных, которых нет в контексте.',
                    'После раздела «Советы по улучшению продаж» обязательно заверши ответ отдельной строкой: '.self::COMPLETION_MARKER,
                ]),
            ],
            [
                'role' => 'user',
                'content' => "Контекст amoCRM и проекта:\n\n".json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            ],
        ];

        $aiUser = F5AiModels::resolveUser($project);

        $emit('status', 'Данные собраны. ИИ анализирует аккаунт…');

        // Таймаут снят полностью (0): ИИ доводит аудит крупного аккаунта до конца.
        $aiOptions = [
            'user' => $aiUser,
            'large' => true,
            'temperature' => 0.25,
            'max_tokens' => (int) config('f5ai.max_tokens_amocrm_audit', 0),
            'timeout' => 0,
            'on_wait' => static function () use ($emit): void {
                $emit('status', 'ИИ анализирует аккаунт… это может занять несколько минут');
            },
        ];

        $content = $this->completion($messages, $aiOptions, $onEvent);

        if (! str_contains($content, self::COMPLETION_MARKER)) {
            $emit('status', 'Ответ длинный — запрашиваю продолжение…');

            if ($onEvent !== null) {
                $emit('chunk', "\n\n");
            }

            $continuation = $this->completion([
                ...$messages,
                [
                    'role' => 'assistant',
                    'content' => $content,
                ],
                [
                    'role' => 'user',
                    'content' => 'Ответ оборвался. Продолжи ровно с места обрыва, не повторяй уже написанное, и обязательно заверши строкой '.self::COMPLETION_MARKER,
                ],
            ], [...$aiOptions, 'temperature' => 0.2], $onEvent);

            $content = rtrim($content)."\n\n".ltrim($continuation);
        }

        $content = trim(str_replace(self::COMPLETION_MARKER, '', $content));

        $account->forceFill([
            'last_ai_audit_at' => now(),
            'last_ai_audit_result' => $content,
        ])->save();

        $this->storeInAiChat(
            $project,
            $selectedPipelines,
            $includeFields,
            $content,
            $userId
        );

        return [
            'content' => $content,
            'audited_at' => $account->last_ai_audit_at?->timezone(config('app.timezone'))->format('d.m.Y H:i') ?? now()->format('d.m.Y H:i'),
        ];
    }

    /**
     * Обычный или потоковый ответ ИИ — в зависимости от наличия слушателя событий.
     *
     * @param  list<array<string, mixed>>  $messages
     * @param  array<string, mixed>  $options
     * @param  callable(string $type, string $text): void|null  $onEvent
     */
    private function completion(array $messages, array $options, ?callable $onEvent): string
    {
        if ($onEvent === null) {
            return $this->f5AiService->chatCompletion($messages, $options);
        }

        return $this->f5AiService->chatCompletionStreamed(
            $messages,
            $options,
            static fn (string $delta) => $onEvent('chunk', $delta)
        );
    }

    /**
     * @param  list<array<string, mixed>>  $selectedPipelines
     */
    private function storeInAiChat(
        Project $project,
        array $selectedPipelines,
        bool $includeFields,
        string $content,
        ?int $userId
    ): void {
        $pipelineNames = collect($selectedPipelines)
            ->map(fn (array $pipeline) => trim((string) ($pipeline['name'] ?? '')))
            ->filter()
            ->values()
            ->all();

        $pipelinesLabel = $pipelineNames === []
            ? 'выбранные воронки'
            : implode(', ', $pipelineNames);

        $fieldsLabel = $includeFields ? 'с полями' : 'без полей';

        $project->aiMessages()->create([
            'thread' => ProjectAiMessage::THREAD_PROJECT,
            'user_id' => $userId,
            'role' => ProjectAiMessage::ROLE_USER,
            'content' => "AI-аудит amoCRM ({$pipelinesLabel}; {$fieldsLabel})",
            'status' => ProjectAiMessage::STATUS_COMPLETED,
        ]);

        $project->aiMessages()->create([
            'thread' => ProjectAiMessage::THREAD_PROJECT,
            'user_id' => $userId,
            'role' => ProjectAiMessage::ROLE_ASSISTANT,
            'content' => $content,
            'status' => ProjectAiMessage::STATUS_COMPLETED,
            'changes' => null,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $pipelines
     * @return list<array<string, mixed>>
     */
    private function pipelineContext(array $pipelines): array
    {
        return collect($pipelines)->map(function (array $pipeline) {
            $statuses = $pipeline['_embedded']['statuses'] ?? [];

            return [
                'id' => $pipeline['id'] ?? null,
                'name' => $pipeline['name'] ?? null,
                'is_main' => $pipeline['is_main'] ?? null,
                'is_unsorted_on' => $pipeline['is_unsorted_on'] ?? null,
                'statuses' => collect(is_array($statuses) ? $statuses : [])
                    ->reject(fn ($status) => (int) ($status['type'] ?? 0) === 1)
                    ->map(fn ($status) => [
                        'id' => $status['id'] ?? null,
                        'name' => $status['name'] ?? null,
                        'sort' => $status['sort'] ?? null,
                        'is_editable' => $status['is_editable'] ?? null,
                    ])
                    ->values()
                    ->all(),
            ];
        })->values()->all();
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function customFieldsContext(AmoCrmClient $client): array
    {
        $fieldsByEntity = [];

        foreach (AmoCrmMapper::ENTITY_MAP as $amoEntityType) {
            $fieldsByEntity[$amoEntityType] = collect($client->getCustomFields($amoEntityType))
                ->reject(fn (array $field) => ($field['is_predefined'] ?? false) === true)
                ->map(fn (array $field) => [
                    'id' => $field['id'] ?? null,
                    'name' => $field['name'] ?? null,
                    'type' => $field['type'] ?? null,
                    'is_api_only' => $field['is_api_only'] ?? null,
                    'is_deletable' => $field['is_deletable'] ?? null,
                    'enums_count' => is_array($field['enums'] ?? null) ? count($field['enums']) : 0,
                ])
                ->take(120)
                ->values()
                ->all();
        }

        return $fieldsByEntity;
    }
}
