<?php

namespace App\Services;

use App\Exceptions\AmoCrmException;
use App\Models\Project;
use App\Models\ProjectAmocrmAccount;
use App\Models\ProjectAmocrmAuditExport;
use App\Support\LongRunningRequest;

class AmoCrmAccountSnapshotService
{
    /** До 100 000 сделок (250/страница) — полная выгрузка, не первая страница на 250. */
    private const LEADS_MAX_PAGES = 400;

    /** До 50 000 открытых задач (без limit amoCRM отдаёт только 50). */
    private const TASKS_MAX_PAGES = 200;

    /** До 500 последних событий. */
    private const EVENTS_MAX_PAGES = 5;

    /**
     * Полная выгрузка данных аккаунта для аналитики.
     *
     * Все сделки, открытые задачи, события и справочники сохраняются в БД проекта
     * (project_amocrm_audit_exports) и автоматически удаляются через сутки —
     * сам проект при этом не затрагивается.
     *
     * @param  callable(array{stage: string, loaded: int}): void|null  $onProgress
     * @return array<string, mixed>
     */
    public function sync(Project $project, ?callable $onProgress = null): array
    {
        $account = $project->amocrmAccount;

        if (! $account instanceof ProjectAmocrmAccount) {
            throw new AmoCrmException('Сначала подключите аккаунт amoCRM к проекту.');
        }

        LongRunningRequest::disableTimeouts();

        $previous = is_array($account->account_snapshot) ? $account->account_snapshot : [];
        $client = new AmoCrmClient($account);
        $bundle = $client->fetchSnapshotBundle();
        $errors = [];
        $reused = [];

        $accountData = $this->sectionValue($bundle['account'] ?? null, $previous['account'] ?? null, 'account', $errors, $reused);
        $rawPipelines = $this->sectionValue($bundle['pipelines'] ?? null, $previous['pipelines'] ?? [], 'pipelines', $errors, $reused);
        $users = $this->sectionValue($bundle['users'] ?? null, $previous['users'] ?? [], 'users', $errors, $reused);
        $groups = $this->sectionValue($bundle['groups'] ?? null, $previous['groups'] ?? [], 'groups', $errors, $reused);
        $widgets = $this->sectionValue($bundle['widgets'] ?? null, $previous['widgets'] ?? [], 'widgets', $errors, $reused);
        $webhooks = $this->sectionValue($bundle['webhooks'] ?? null, $previous['webhooks'] ?? [], 'webhooks', $errors, $reused);
        $sources = $this->sectionValue($bundle['sources'] ?? null, $previous['sources'] ?? [], 'sources', $errors, $reused);
        $lossReasons = $this->sectionValue($bundle['loss_reasons'] ?? null, $previous['loss_reasons'] ?? [], 'loss_reasons', $errors, $reused);

        // Полная выгрузка сделок, задач и событий (все страницы вместо первых 250/50).
        $leadsSection = $this->attempt(
            fn () => $client->getAllLeadsCompact(
                self::LEADS_MAX_PAGES,
                $onProgress === null ? null : fn (int $loaded) => $onProgress(['stage' => 'leads', 'loaded' => $loaded])
            ),
            ['leads' => [], 'truncated' => false]
        );
        $tasksSection = $this->attempt(
            fn () => $client->getAllOpenTasksCompact(
                self::TASKS_MAX_PAGES,
                $onProgress === null ? null : fn (int $loaded) => $onProgress(['stage' => 'tasks', 'loaded' => $loaded])
            ),
            ['tasks' => [], 'truncated' => false]
        );
        $eventsSection = $this->attempt(
            fn () => $client->getRecentEvents(self::EVENTS_MAX_PAGES),
            []
        );

        $customFieldsOk = ($bundle['custom_fields']['ok'] ?? false) === true;
        if ($customFieldsOk) {
            $customFields = $this->mapCustomFieldsBundle(
                is_array($bundle['custom_fields']['value'] ?? null)
                    ? $bundle['custom_fields']['value']
                    : ['leads' => [], 'contacts' => [], 'companies' => []]
            );
        } else {
            $previousFields = $previous['custom_fields'] ?? null;
            $hasPreviousFields = is_array($previousFields) && (
                ($previousFields['leads'] ?? []) !== []
                || ($previousFields['contacts'] ?? []) !== []
                || ($previousFields['companies'] ?? []) !== []
            );
            $cfError = $bundle['custom_fields']['error'] ?? 'ошибка';

            if ($hasPreviousFields) {
                $customFields = $previousFields;
                $reused[] = 'custom_fields';
                $errors[] = 'custom_fields: использован предыдущий снимок ('.$cfError.')';
            } else {
                $customFields = ['leads' => [], 'contacts' => [], 'companies' => []];
                $errors[] = 'custom_fields: '.$cfError;
            }
        }

        $previousTags = $previous['tags'] ?? [];
        $leadTags = $this->sectionValue($bundle['lead_tags'] ?? null, $previousTags['leads'] ?? [], 'lead_tags', $errors, $reused);
        $contactTags = $this->sectionValue($bundle['contact_tags'] ?? null, $previousTags['contacts'] ?? [], 'contact_tags', $errors, $reused);

        // pipelines из предыдущего снимка уже обогащены — не прогоняем enrich повторно по ним.
        $pipelinesWereReused = in_array('pipelines', $reused, true);
        $enrichedPipelines = $pipelinesWereReused
            ? (is_array($rawPipelines) ? $rawPipelines : [])
            : $this->enrichPipelines(is_array($rawPipelines) ? $rawPipelines : []);

        if (($leadsSection['ok'] ?? false) === true) {
            $leads = $leadsSection['value']['leads'] ?? [];
            $leadsTruncated = (bool) ($leadsSection['value']['truncated'] ?? false);
            $leadsAnalytics = $this->buildLeadsAnalytics(
                $enrichedPipelines,
                is_array($leads) ? $leads : [],
                is_array($lossReasons) ? $lossReasons : []
            );
            $pipelines = $this->attachLeadCountsToPipelines(
                $enrichedPipelines,
                $leadsAnalytics['by_status'] ?? []
            );
        } elseif (is_array($previous['leads_analytics'] ?? null) && $previous['leads_analytics'] !== []) {
            $leads = [];
            $leadsTruncated = false;
            $leadsAnalytics = $previous['leads_analytics'];
            $pipelines = $enrichedPipelines;
            $reused[] = 'leads';
            $errors[] = 'leads: использован предыдущий снимок'
                .(! empty($leadsSection['error']) ? ' ('.$leadsSection['error'].')' : '');
        } else {
            $leads = [];
            $leadsTruncated = false;
            $leadsAnalytics = $this->buildLeadsAnalytics($enrichedPipelines, [], is_array($lossReasons) ? $lossReasons : []);
            $pipelines = $enrichedPipelines;
            $errors[] = 'leads: '.($leadsSection['error'] ?? 'ошибка');
        }

        if (($tasksSection['ok'] ?? false) === true) {
            $tasks = $tasksSection['value']['tasks'] ?? [];
            $tasks = is_array($tasks) ? $tasks : [];
            $tasksTruncated = (bool) ($tasksSection['value']['truncated'] ?? false);
            $tasksAnalytics = $this->buildTasksAnalytics($tasks);
        } elseif (is_array($previous['tasks_analytics'] ?? null) && $previous['tasks_analytics'] !== []) {
            $tasks = is_array($previous['tasks_open_sample'] ?? null) ? $previous['tasks_open_sample'] : [];
            $tasksTruncated = false;
            $tasksAnalytics = $previous['tasks_analytics'];
            $reused[] = 'tasks';
            $errors[] = 'tasks: использован предыдущий снимок'
                .(! empty($tasksSection['error']) ? ' ('.$tasksSection['error'].')' : '');
        } else {
            $tasks = [];
            $tasksTruncated = false;
            $tasksAnalytics = $this->buildTasksAnalytics([]);
            $errors[] = 'tasks: '.($tasksSection['error'] ?? 'ошибка');
        }

        $events = $this->sectionValue(
            $eventsSection,
            $previous['events_recent'] ?? [],
            'events',
            $errors,
            $reused
        );

        $expiresAt = now()->addHours(ProjectAmocrmAuditExport::TTL_HOURS);

        $payload = [
            'fetched_at' => now()->toIso8601String(),
            'account' => $accountData,
            'users' => is_array($users) ? $users : [],
            'groups' => is_array($groups) ? $groups : [],
            'pipelines' => $pipelines,
            'custom_fields' => $customFields,
            'widgets' => is_array($widgets) ? $widgets : [],
            'webhooks' => is_array($webhooks) ? $webhooks : [],
            'sources' => is_array($sources) ? $sources : [],
            'loss_reasons' => is_array($lossReasons) ? $lossReasons : [],
            'tags' => [
                'leads' => is_array($leadTags) ? $leadTags : [],
                'contacts' => is_array($contactTags) ? $contactTags : [],
            ],
            'leads' => $leads,
            'leads_truncated' => $leadsTruncated,
            'leads_analytics' => $leadsAnalytics,
            'tasks_open' => $tasks,
            'tasks_truncated' => $tasksTruncated,
            'tasks_analytics' => $tasksAnalytics,
            'events_recent' => is_array($events) ? $events : [],
            'digital_pipeline' => $this->digitalPipelineFromStageTips($pipelines),
            'errors' => array_values(array_unique($errors)),
            'reused_sections' => array_values(array_unique($reused)),
        ];

        $payload['summary'] = $this->buildSummary($payload);

        // Полная выгрузка живёт в отдельной таблице и удаляется через сутки.
        $export = $project->amocrmAuditExports()->create([
            'payload' => $payload,
            'fetched_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        $payload['summary']['export_expires_at'] = $expiresAt->toIso8601String();

        $this->purgeExpired($project, $export->id);

        // На аккаунте остаётся компактная версия без сырых списков — для панели и быстрого доступа.
        $snapshot = $payload;
        unset($snapshot['leads'], $snapshot['tasks_open']);
        $snapshot['tasks_open_sample'] = array_slice($tasks, 0, 100);
        $snapshot['summary'] = $payload['summary'];

        $account->forceFill([
            'account_snapshot' => $snapshot,
            'account_snapshot_at' => now(),
        ])->save();

        return $payload;
    }

    /**
     * Удаляет просроченные выгрузки (старше суток) и предыдущие выгрузки проекта.
     * Дублирует model:prune на случай, если планировщик не настроен.
     */
    public function purgeExpired(?Project $project = null, ?int $keepExportId = null): int
    {
        $deleted = ProjectAmocrmAuditExport::query()
            ->where('expires_at', '<=', now())
            ->delete();

        if ($project !== null && $keepExportId !== null) {
            $deleted += ProjectAmocrmAuditExport::query()
                ->where('project_id', $project->id)
                ->where('id', '!=', $keepExportId)
                ->delete();
        }

        return $deleted;
    }

    /**
     * @param  array{ok?: bool, value?: mixed, error?: ?string}|null  $section
     * @param  list<string>  $errors
     * @param  list<string>  $reused
     */
    private function sectionValue(mixed $section, mixed $previous, string $label, array &$errors, array &$reused): mixed
    {
        if (is_array($section) && ($section['ok'] ?? false) === true) {
            return $section['value'] ?? null;
        }

        $error = is_array($section) ? ($section['error'] ?? 'ошибка') : 'ошибка';

        if ($previous !== null && $previous !== [] && $previous !== '') {
            $reused[] = $label;
            $errors[] = $label.': использован предыдущий снимок ('.$error.')';

            return $previous;
        }

        $errors[] = $label.': '.$error;

        return is_array($section) ? ($section['value'] ?? null) : null;
    }

    /**
     * @return array{ok: bool, value: mixed, error: ?string}
     */
    private function attempt(callable $callback, mixed $empty = []): array
    {
        try {
            return ['ok' => true, 'value' => $callback(), 'error' => null];
        } catch (\Throwable $exception) {
            return ['ok' => false, 'value' => $empty, 'error' => $exception->getMessage()];
        }
    }

    /**
     * Свежая полная выгрузка переиспользуется; иначе собирается заново.
     *
     * @param  callable(array{stage: string, loaded: int}): void|null  $onProgress
     * @return array<string, mixed>
     */
    public function syncIfStale(Project $project, int $maxAgeMinutes = 20, ?callable $onProgress = null): array
    {
        $latest = $project->amocrmAuditExports()->first();

        if (
            $latest instanceof ProjectAmocrmAuditExport
            && ! $latest->isExpired()
            && is_array($latest->payload)
            && $latest->payload !== []
            && $latest->fetched_at
            && $latest->fetched_at->greaterThan(now()->subMinutes($maxAgeMinutes))
        ) {
            return $latest->payload;
        }

        return $this->sync($project, $onProgress);
    }

    /**
     * Сжатый контекст для ИИ-аудита (без сырых токенов и лишних полей).
     * Аналитика посчитана по ПОЛНОЙ выгрузке сделок и задач.
     *
     * @param  array<string, mixed>|null  $snapshot
     * @return array<string, mixed>
     */
    public function auditContext(?array $snapshot): array
    {
        if (! is_array($snapshot) || $snapshot === []) {
            return [];
        }

        return [
            'summary' => $snapshot['summary'] ?? [],
            'account' => $snapshot['account'] ?? null,
            'users_count' => is_array($snapshot['users'] ?? null) ? count($snapshot['users']) : 0,
            'users' => collect($snapshot['users'] ?? [])
                ->map(fn ($user) => [
                    'id' => $user['id'] ?? null,
                    'name' => $user['name'] ?? null,
                    'email' => $user['email'] ?? null,
                    'is_active' => $user['rights']['is_active'] ?? $user['is_active'] ?? null,
                    'role_id' => $user['rights']['role_id'] ?? null,
                    'group_id' => $user['rights']['group_id'] ?? null,
                ])
                ->take(80)
                ->values()
                ->all(),
            'groups' => collect($snapshot['groups'] ?? [])->take(40)->values()->all(),
            'pipelines' => collect($snapshot['pipelines'] ?? [])
                ->map(fn ($pipeline) => [
                    'id' => $pipeline['id'] ?? null,
                    'name' => $pipeline['name'] ?? null,
                    'is_main' => $pipeline['is_main'] ?? null,
                    'is_unsorted_on' => $pipeline['is_unsorted_on'] ?? null,
                    'statuses' => collect($pipeline['statuses'] ?? [])
                        ->map(fn ($status) => [
                            'id' => $status['id'] ?? null,
                            'name' => $status['name'] ?? null,
                            'sort' => $status['sort'] ?? null,
                            'type' => $status['type'] ?? null,
                            'descriptions' => $status['descriptions'] ?? [],
                            'leads_count' => $status['leads_count'] ?? $status['leads_in_sample'] ?? 0,
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'custom_fields' => $snapshot['custom_fields'] ?? [],
            'widgets' => collect($snapshot['widgets'] ?? [])
                ->map(fn ($widget) => [
                    'code' => $widget['code'] ?? null,
                    'name' => $widget['name'] ?? $widget['code'] ?? null,
                    'is_active' => $widget['settings']['is_active'] ?? $widget['is_active'] ?? null,
                ])
                ->take(100)
                ->values()
                ->all(),
            'webhooks' => collect($snapshot['webhooks'] ?? [])
                ->map(fn ($hook) => [
                    'id' => $hook['id'] ?? null,
                    'destination' => $hook['destination'] ?? null,
                    'settings' => $hook['settings'] ?? [],
                    'disabled' => $hook['disabled'] ?? null,
                ])
                ->take(60)
                ->values()
                ->all(),
            'sources' => collect($snapshot['sources'] ?? [])->take(60)->values()->all(),
            'loss_reasons' => collect($snapshot['loss_reasons'] ?? [])->take(60)->values()->all(),
            'tags' => [
                'leads' => collect($snapshot['tags']['leads'] ?? [])->pluck('name')->filter()->take(80)->values()->all(),
                'contacts' => collect($snapshot['tags']['contacts'] ?? [])->pluck('name')->filter()->take(80)->values()->all(),
            ],
            'leads_analytics' => $snapshot['leads_analytics'] ?? [],
            'leads_truncated' => (bool) ($snapshot['leads_truncated'] ?? false),
            'tasks_analytics' => $snapshot['tasks_analytics'] ?? [],
            'tasks_truncated' => (bool) ($snapshot['tasks_truncated'] ?? false),
            'events_recent' => collect($snapshot['events_recent'] ?? [])
                ->map(fn ($event) => [
                    'type' => $event['type'] ?? null,
                    'entity_id' => $event['entity_id'] ?? null,
                    'created_at' => $event['created_at'] ?? null,
                    'value_after' => $event['value_after'] ?? null,
                    'value_before' => $event['value_before'] ?? null,
                ])
                ->take(100)
                ->values()
                ->all(),
            'tasks_open_sample' => collect($snapshot['tasks_open'] ?? $snapshot['tasks_open_sample'] ?? [])
                ->map(fn ($task) => [
                    'id' => $task['id'] ?? null,
                    'text' => mb_substr((string) ($task['text'] ?? ''), 0, 160),
                    'entity_type' => $task['entity_type'] ?? null,
                    'entity_id' => $task['entity_id'] ?? null,
                    'responsible_user_id' => $task['responsible_user_id'] ?? null,
                    'complete_till' => $task['complete_till'] ?? null,
                    'task_type_id' => $task['task_type_id'] ?? null,
                ])
                ->take(60)
                ->values()
                ->all(),
            'digital_pipeline' => [
                'available' => (bool) ($snapshot['digital_pipeline']['available'] ?? false),
                'note' => $snapshot['digital_pipeline']['note'] ?? null,
                'stage_tips' => array_slice($snapshot['digital_pipeline']['stage_tips'] ?? [], 0, 80),
                'pipelines' => $snapshot['digital_pipeline']['pipelines'] ?? [],
            ],
            'fetch_errors' => $snapshot['errors'] ?? [],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $pipelines
     * @return list<array<string, mixed>>
     */
    private function enrichPipelines(array $pipelines): array
    {
        $result = [];

        foreach ($pipelines as $pipeline) {
            if (! is_array($pipeline)) {
                continue;
            }

            $pipelineId = (int) ($pipeline['id'] ?? 0);
            $statuses = $pipeline['_embedded']['statuses'] ?? [];
            $mappedStatuses = [];

            foreach (is_array($statuses) ? $statuses : [] as $status) {
                if (! is_array($status)) {
                    continue;
                }

                $mappedStatuses[] = [
                    'id' => (int) ($status['id'] ?? 0) ?: null,
                    'name' => $status['name'] ?? null,
                    'sort' => $status['sort'] ?? null,
                    'type' => $status['type'] ?? null,
                    'color' => $status['color'] ?? null,
                    'is_editable' => $status['is_editable'] ?? null,
                    'descriptions' => $this->normalizeDescriptions($status['descriptions'] ?? []),
                ];
            }

            $result[] = [
                'id' => $pipelineId ?: null,
                'name' => $pipeline['name'] ?? null,
                'is_main' => $pipeline['is_main'] ?? null,
                'is_unsorted_on' => $pipeline['is_unsorted_on'] ?? null,
                'sort' => $pipeline['sort'] ?? null,
                'statuses' => $mappedStatuses,
            ];
        }

        return $result;
    }

    /**
     * Официального API Digital Pipeline нет — для аудита отдаём подсказки этапов как сигналы автоматизации.
     *
     * @param  list<array<string, mixed>>  $pipelines
     * @return array<string, mixed>
     */
    private function digitalPipelineFromStageTips(array $pipelines): array
    {
        $tips = [];

        foreach ($pipelines as $pipeline) {
            foreach ($pipeline['statuses'] ?? [] as $status) {
                if (empty($status['descriptions'])) {
                    continue;
                }

                $tips[] = [
                    'pipeline_id' => $pipeline['id'] ?? null,
                    'pipeline_name' => $pipeline['name'] ?? null,
                    'status_id' => $status['id'] ?? null,
                    'status_name' => $status['name'] ?? null,
                    'descriptions' => $status['descriptions'],
                ];
            }
        }

        return [
            'available' => false,
            'pipelines' => [],
            'stage_tips' => array_slice($tips, 0, 120),
            'note' => 'Публичный API amoCRM не отдаёт настройки Digital Pipeline. '
                .'В снимке: подсказки этапов (descriptions), виджеты, вебхуки, события и полная выгрузка сделок.',
        ];
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $bundle
     * @return array<string, list<array<string, mixed>>>
     */
    private function mapCustomFieldsBundle(array $bundle): array
    {
        $result = [];

        foreach ($bundle as $entity => $fields) {
            $result[$entity] = collect(is_array($fields) ? $fields : [])
                ->map(fn (array $field) => [
                    'id' => $field['id'] ?? null,
                    'name' => $field['name'] ?? null,
                    'type' => $field['type'] ?? null,
                    'is_predefined' => $field['is_predefined'] ?? null,
                    'is_api_only' => $field['is_api_only'] ?? null,
                    'required_statuses' => $field['required_statuses'] ?? [],
                    'enums_count' => is_array($field['enums'] ?? null) ? count($field['enums']) : 0,
                    'enums' => collect($field['enums'] ?? [])
                        ->map(fn ($enum) => is_array($enum) ? ($enum['value'] ?? null) : null)
                        ->filter()
                        ->take(30)
                        ->values()
                        ->all(),
                ])
                ->take(150)
                ->values()
                ->all();
        }

        return $result;
    }

    /**
     * Аналитика по ПОЛНОЙ выгрузке сделок (не по выборке 250).
     *
     * @param  list<array<string, mixed>>  $pipelines
     * @param  list<array<string, mixed>>  $leads
     * @param  list<array<string, mixed>>  $lossReasons
     * @return array<string, mixed>
     */
    private function buildLeadsAnalytics(array $pipelines, array $leads, array $lossReasons = []): array
    {
        $pipelineNames = collect($pipelines)->mapWithKeys(
            fn ($p) => [(int) ($p['id'] ?? 0) => (string) ($p['name'] ?? '')]
        );
        $statusNames = [];
        $statusPipeline = [];

        foreach ($pipelines as $pipeline) {
            foreach ($pipeline['statuses'] ?? [] as $status) {
                $statusNames[(int) ($status['id'] ?? 0)] = (string) ($status['name'] ?? '');
                $statusPipeline[(int) ($status['id'] ?? 0)] = (int) ($pipeline['id'] ?? 0);
            }
        }

        $lossReasonNames = collect($lossReasons)->mapWithKeys(
            fn ($reason) => [(int) ($reason['id'] ?? 0) => (string) ($reason['name'] ?? '')]
        );

        $byPipeline = [];
        $byStatus = [];
        $byResponsible = [];
        $byLossReason = [];
        $createdByMonth = [];
        $closedByMonth = [];
        $prices = [];
        $closed = 0;
        $open = 0;

        foreach ($leads as $lead) {
            if (! is_array($lead)) {
                continue;
            }

            $pipelineId = (int) ($lead['pipeline_id'] ?? 0);
            $statusId = (int) ($lead['status_id'] ?? 0);
            $responsible = (int) ($lead['responsible_user_id'] ?? 0);
            $price = isset($lead['price']) ? (float) $lead['price'] : null;
            $closedAt = $lead['closed_at'] ?? null;
            $isClosed = ! empty($closedAt);

            if ($isClosed) {
                $closed++;
            } else {
                $open++;
            }

            if ($price !== null) {
                $prices[] = $price;
            }

            if (! isset($byPipeline[$pipelineId])) {
                $byPipeline[$pipelineId] = ['count' => 0, 'open' => 0, 'closed' => 0, 'price_sum' => 0.0];
            }
            $byPipeline[$pipelineId]['count']++;
            $byPipeline[$pipelineId][$isClosed ? 'closed' : 'open']++;
            $byPipeline[$pipelineId]['price_sum'] += $price ?? 0.0;

            if (! isset($byStatus[$statusId])) {
                $byStatus[$statusId] = ['count' => 0, 'price_sum' => 0.0];
            }
            $byStatus[$statusId]['count']++;
            $byStatus[$statusId]['price_sum'] += $price ?? 0.0;

            if (! isset($byResponsible[$responsible])) {
                $byResponsible[$responsible] = ['count' => 0, 'price_sum' => 0.0];
            }
            $byResponsible[$responsible]['count']++;
            $byResponsible[$responsible]['price_sum'] += $price ?? 0.0;

            $lossReasonId = (int) ($lead['loss_reason_id'] ?? 0);
            if ($isClosed && $lossReasonId > 0) {
                $byLossReason[$lossReasonId] = ($byLossReason[$lossReasonId] ?? 0) + 1;
            }

            $createdAt = $lead['created_at'] ?? null;
            if (is_numeric($createdAt) && (int) $createdAt > 0) {
                $month = date('Y-m', (int) $createdAt);
                $createdByMonth[$month] = ($createdByMonth[$month] ?? 0) + 1;
            }

            if ($isClosed && is_numeric($closedAt) && (int) $closedAt > 0) {
                $month = date('Y-m', (int) $closedAt);
                $closedByMonth[$month] = ($closedByMonth[$month] ?? 0) + 1;
            }
        }

        ksort($createdByMonth);
        ksort($closedByMonth);

        return [
            'sample_size' => count($leads),
            'total' => count($leads),
            'open_in_sample' => $open,
            'closed_in_sample' => $closed,
            'open' => $open,
            'closed' => $closed,
            'price_sum_in_sample' => array_sum($prices),
            'price_avg_in_sample' => $prices === [] ? null : round(array_sum($prices) / count($prices), 2),
            'price_sum' => array_sum($prices),
            'price_avg' => $prices === [] ? null : round(array_sum($prices) / count($prices), 2),
            'by_pipeline' => collect($byPipeline)
                ->map(fn ($row, $id) => [
                    'pipeline_id' => (int) $id,
                    'name' => $pipelineNames[(int) $id] ?? null,
                    'count' => $row['count'],
                    'open' => $row['open'],
                    'closed' => $row['closed'],
                    'price_sum' => round($row['price_sum'], 2),
                ])
                ->sortByDesc('count')
                ->values()
                ->all(),
            'by_status' => collect($byStatus)
                ->map(fn ($row, $id) => [
                    'status_id' => (int) $id,
                    'name' => $statusNames[(int) $id] ?? null,
                    'pipeline_id' => $statusPipeline[(int) $id] ?? null,
                    'count' => $row['count'],
                    'price_sum' => round($row['price_sum'], 2),
                ])
                ->sortByDesc('count')
                ->take(80)
                ->values()
                ->all(),
            'by_responsible' => collect($byResponsible)
                ->map(fn ($row, $id) => [
                    'responsible_user_id' => (int) $id,
                    'count' => $row['count'],
                    'price_sum' => round($row['price_sum'], 2),
                ])
                ->sortByDesc('count')
                ->take(40)
                ->values()
                ->all(),
            'by_loss_reason' => collect($byLossReason)
                ->map(fn ($count, $id) => [
                    'loss_reason_id' => (int) $id,
                    'name' => $lossReasonNames[(int) $id] ?? null,
                    'count' => $count,
                ])
                ->sortByDesc('count')
                ->take(20)
                ->values()
                ->all(),
            'created_by_month' => collect($createdByMonth)
                ->map(fn ($count, $month) => ['month' => $month, 'count' => $count])
                ->values()
                ->all(),
            'closed_by_month' => collect($closedByMonth)
                ->map(fn ($count, $month) => ['month' => $month, 'count' => $count])
                ->values()
                ->all(),
            'recent_leads' => collect($leads)
                ->sortByDesc(fn (array $lead) => (int) ($lead['created_at'] ?? $lead['id'] ?? 0))
                ->take(40)
                ->map(fn (array $lead) => [
                    'id' => $lead['id'] ?? null,
                    'name' => $lead['name'] ?? null,
                    'price' => $lead['price'] ?? null,
                    'pipeline_id' => $lead['pipeline_id'] ?? null,
                    'status_id' => $lead['status_id'] ?? null,
                    'responsible_user_id' => $lead['responsible_user_id'] ?? null,
                    'created_at' => $lead['created_at'] ?? null,
                    'updated_at' => $lead['updated_at'] ?? null,
                    'closed_at' => $lead['closed_at'] ?? null,
                    'loss_reason_id' => $lead['loss_reason_id'] ?? null,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Аналитика по ПОЛНОЙ выгрузке открытых задач.
     *
     * @param  list<array<string, mixed>>  $tasks
     * @return array<string, mixed>
     */
    private function buildTasksAnalytics(array $tasks): array
    {
        $now = time();
        $overdue = 0;
        $byResponsible = [];
        $byType = [];

        foreach ($tasks as $task) {
            if (! is_array($task)) {
                continue;
            }

            $completeTill = $task['complete_till'] ?? null;
            if (is_numeric($completeTill) && (int) $completeTill > 0 && (int) $completeTill < $now) {
                $overdue++;
            }

            $responsible = (int) ($task['responsible_user_id'] ?? 0);
            $byResponsible[$responsible] = ($byResponsible[$responsible] ?? 0) + 1;

            $type = (int) ($task['task_type_id'] ?? 0);
            $byType[$type] = ($byType[$type] ?? 0) + 1;
        }

        return [
            'total_open' => count($tasks),
            'overdue' => $overdue,
            'by_responsible' => collect($byResponsible)
                ->map(fn ($count, $id) => [
                    'responsible_user_id' => (int) $id,
                    'count' => $count,
                ])
                ->sortByDesc('count')
                ->take(40)
                ->values()
                ->all(),
            'by_type' => collect($byType)
                ->map(fn ($count, $id) => [
                    'task_type_id' => (int) $id,
                    'count' => $count,
                ])
                ->sortByDesc('count')
                ->take(20)
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $pipelines
     * @param  list<array<string, mixed>>  $byStatus
     * @return list<array<string, mixed>>
     */
    private function attachLeadCountsToPipelines(array $pipelines, array $byStatus): array
    {
        $counts = collect($byStatus)->mapWithKeys(
            fn ($row) => [(int) ($row['status_id'] ?? 0) => (int) ($row['count'] ?? 0)]
        );

        foreach ($pipelines as &$pipeline) {
            foreach ($pipeline['statuses'] as &$status) {
                $status['leads_count'] = $counts[(int) ($status['id'] ?? 0)] ?? 0;
            }
            unset($status);
        }
        unset($pipeline);

        return $pipelines;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function buildSummary(array $snapshot): array
    {
        $pipelines = $snapshot['pipelines'] ?? [];
        $statusesWithTips = 0;
        $statusesTotal = 0;

        foreach ($pipelines as $pipeline) {
            foreach ($pipeline['statuses'] ?? [] as $status) {
                $statusesTotal++;

                if (! empty($status['descriptions'])) {
                    $statusesWithTips++;
                }
            }
        }

        $dp = $snapshot['digital_pipeline'] ?? [];
        $dpTriggers = collect($dp['pipelines'] ?? [])->sum('triggers_count');

        $reused = is_array($snapshot['reused_sections'] ?? null) ? $snapshot['reused_sections'] : [];
        $errors = is_array($snapshot['errors'] ?? null) ? $snapshot['errors'] : [];

        $leadsTotal = (int) ($snapshot['leads_analytics']['total'] ?? $snapshot['leads_analytics']['sample_size'] ?? 0);
        $tasksTotal = (int) ($snapshot['tasks_analytics']['total_open'] ?? 0);

        return [
            'pipelines' => count($pipelines),
            'statuses' => $statusesTotal,
            'statuses_with_tips' => $statusesWithTips,
            'users' => is_array($snapshot['users'] ?? null) ? count($snapshot['users']) : 0,
            'widgets' => is_array($snapshot['widgets'] ?? null) ? count($snapshot['widgets']) : 0,
            'webhooks' => is_array($snapshot['webhooks'] ?? null) ? count($snapshot['webhooks']) : 0,
            'sources' => is_array($snapshot['sources'] ?? null) ? count($snapshot['sources']) : 0,
            'loss_reasons' => is_array($snapshot['loss_reasons'] ?? null) ? count($snapshot['loss_reasons']) : 0,
            'leads_in_sample' => $leadsTotal,
            'leads_total' => $leadsTotal,
            'leads_truncated' => (bool) ($snapshot['leads_truncated'] ?? false),
            'digital_pipeline_available' => (bool) ($dp['available'] ?? false),
            'digital_pipeline_triggers' => (int) $dpTriggers,
            'events_recent' => is_array($snapshot['events_recent'] ?? null) ? count($snapshot['events_recent']) : 0,
            'tasks_open_sample' => $tasksTotal,
            'tasks_open_total' => $tasksTotal,
            'tasks_overdue' => (int) ($snapshot['tasks_analytics']['overdue'] ?? 0),
            'tasks_truncated' => (bool) ($snapshot['tasks_truncated'] ?? false),
            'errors_count' => count($errors),
            'errors' => array_values(array_slice($errors, 0, 8)),
            'reused_sections' => array_values($reused),
            'is_partial' => $reused !== [] || $errors !== [],
        ];
    }

    /**
     * @param  mixed  $descriptions
     * @return list<array<string, mixed>>
     */
    private function normalizeDescriptions(mixed $descriptions): array
    {
        if (! is_array($descriptions)) {
            return [];
        }

        return collect($descriptions)
            ->map(function ($row) {
                if (is_string($row)) {
                    return ['level' => null, 'text' => mb_substr($row, 0, 500)];
                }

                if (! is_array($row)) {
                    return null;
                }

                return [
                    'level' => $row['level'] ?? $row['type'] ?? null,
                    'text' => mb_substr((string) ($row['description'] ?? $row['text'] ?? $row['value'] ?? ''), 0, 500),
                ];
            })
            ->filter(fn ($row) => is_array($row) && trim((string) ($row['text'] ?? '')) !== '')
            ->values()
            ->all();
    }
}
