<?php

namespace App\Services;

use App\Exceptions\AmoCrmException;
use App\Models\ProjectAmocrmAccount;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class AmoCrmClient
{
    public function __construct(
        private readonly ProjectAmocrmAccount $account
    ) {}

    /**
     * @return array{id: int, name: string, subdomain: string}
     */
    public function fetchAccount(): array
    {
        $data = $this->fetchAccountDetailed();

        return [
            'id' => (int) ($data['id'] ?? 0),
            'name' => (string) ($data['name'] ?? $this->account->subdomain),
            'subdomain' => (string) ($data['subdomain'] ?? $this->account->subdomain),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchAccountDetailed(): array
    {
        $response = $this->request()->get('/api/v4/account', [
            'with' => 'amojo_id,uuid,version,drive_url,is_api_filter_enabled,entity_names',
        ]);

        $this->throwIfFailed($response, 'Не удалось проверить доступ к amoCRM.');

        $data = $response->json() ?? [];

        return is_array($data) ? $data : [];
    }

    /**
     * Мягкий GET: null при ошибке (для best-effort снимка аккаунта).
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    public function tryGet(string $path, array $query = []): ?array
    {
        try {
            $response = $this->request()->timeout(25)->get($path, $query);

            if (! $response->successful()) {
                return null;
            }

            $json = $response->json();

            return is_array($json) ? $json : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getUsers(): array
    {
        return $this->paginateEmbedded('/api/v4/users', 'users', 10);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getUserGroups(): array
    {
        $json = $this->tryGet('/api/v4/users/groups')
            ?? $this->tryGet('/api/v4/groups');

        if ($json === null) {
            return [];
        }

        $groups = $json['_embedded']['groups']
            ?? $json['_embedded']['users_groups']
            ?? $json['groups']
            ?? [];

        return is_array($groups) ? array_values(array_filter($groups, 'is_array')) : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getWidgets(): array
    {
        return $this->paginateEmbedded('/api/v4/widgets', 'widgets', 8);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getWebhooks(): array
    {
        return $this->paginateEmbedded('/api/v4/webhooks', 'webhooks', 5);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getSources(): array
    {
        return $this->paginateEmbedded('/api/v4/sources', 'sources', 5);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getLossReasons(): array
    {
        return $this->paginateEmbedded('/api/v4/leads/loss_reasons', 'loss_reasons', 5);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getTags(string $entityType): array
    {
        return $this->paginateEmbedded("/api/v4/{$entityType}/tags", 'tags', 8);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getEvents(int $limit = 80): array
    {
        $response = $this->request()->get('/api/v4/events', [
            'limit' => min(100, max(1, $limit)),
            'page' => 1,
        ]);

        if (! $response->successful()) {
            return [];
        }

        $events = $response->json('_embedded.events') ?? [];

        return is_array($events) ? array_values(array_filter($events, 'is_array')) : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getOpenTasks(int $limit = 80): array
    {
        $response = $this->request()->get('/api/v4/tasks', [
            'limit' => min(100, max(1, $limit)),
            'page' => 1,
            'filter' => [
                'is_completed' => 0,
            ],
        ]);

        if (! $response->successful()) {
            return [];
        }

        $tasks = $response->json('_embedded.tasks') ?? [];

        return is_array($tasks) ? array_values(array_filter($tasks, 'is_array')) : [];
    }

    /**
     * 250 последних созданных сделок (по created_at).
     *
     * @return list<array<string, mixed>>
     */
    public function getLeadsSample(int $limit = 250): array
    {
        $limit = min(250, max(1, $limit));

        $response = $this->snapshotRequest()->get('/api/v4/leads', [
            'limit' => $limit,
            'page' => 1,
            'order' => ['created_at' => 'desc'],
            'with' => 'loss_reason',
        ]);

        if (! $response->successful()) {
            return [];
        }

        $batch = $response->json('_embedded.leads') ?? [];

        if (! is_array($batch)) {
            return [];
        }

        return array_values(array_filter($batch, 'is_array'));
    }

    /**
     * Полная выгрузка сделок аккаунта для аналитики (все страницы, компактные поля).
     *
     * amoCRM по умолчанию отдаёт только первую страницу (до 250 сделок). Здесь
     * идём по id-курсору, пока записи не закончатся.
     *
     * @param  callable(int $loaded): void|null  $onPage  Колбэк прогресса после каждой страницы.
     * @return array{leads: list<array<string, mixed>>, truncated: bool}
     */
    public function getAllLeadsCompact(int $maxPages = 400, ?callable $onPage = null): array
    {
        $result = $this->paginateCompact(
            '/api/v4/leads',
            'leads',
            [],
            $maxPages,
            fn (array $lead) => $this->compactLead($lead),
            $onPage
        );

        return ['leads' => $result['items'], 'truncated' => $result['truncated']];
    }

    /**
     * Полная выгрузка открытых задач аккаунта (все страницы, компактные поля).
     *
     * Без явного limit amoCRM отдаёт 50 задач — здесь всегда 250/страница и все страницы.
     *
     * @param  callable(int $loaded): void|null  $onPage
     * @return array{tasks: list<array<string, mixed>>, truncated: bool}
     */
    public function getAllOpenTasksCompact(int $maxPages = 200, ?callable $onPage = null): array
    {
        $result = $this->paginateCompact(
            '/api/v4/tasks',
            'tasks',
            [
                'filter' => ['is_completed' => 0],
            ],
            $maxPages,
            fn (array $task) => $this->compactTask($task),
            $onPage
        );

        return ['tasks' => $result['items'], 'truncated' => $result['truncated']];
    }

    /**
     * Последние события аккаунта (несколько страниц, а не одна).
     *
     * @return list<array<string, mixed>>
     */
    public function getRecentEvents(int $maxPages = 5): array
    {
        $all = [];
        $page = 1;

        do {
            $response = $this->request()->timeout(25)->connectTimeout(8)->get('/api/v4/events', [
                'limit' => 100,
                'page' => $page,
            ]);

            if (! $response->successful()) {
                break;
            }

            $batch = $response->json('_embedded.events') ?? [];

            if (! is_array($batch) || $batch === []) {
                break;
            }

            foreach ($batch as $row) {
                if (is_array($row)) {
                    $all[] = $row;
                }
            }

            $page++;
        } while (count($batch) >= 100 && $page <= $maxPages);

        return $all;
    }

    /**
     * Полная выгрузка по id-курсору: amoCRM часто обрывает page=2+ (остаются 250 сделок / 50 задач).
     *
     * @param  array<string, mixed>  $query
     * @param  callable(array<string, mixed>): ?array<string, mixed>  $map
     * @param  callable(int $loaded): void|null  $onPage
     * @return array{items: list<array<string, mixed>>, truncated: bool}
     */
    private function paginateCompact(
        string $path,
        string $embeddedKey,
        array $query,
        int $maxPages,
        callable $map,
        ?callable $onPage = null
    ): array {
        $all = [];
        $truncated = false;
        $afterId = 0;
        $maxItems = max(1, $maxPages) * 250;
        $page = 0;

        while (true) {
            $page++;
            $response = $this->paginatedGet($path, $this->withIdCursor($query, $afterId));

            if (! $response->successful()) {
                if ($page === 1) {
                    throw new AmoCrmException(
                        "Не удалось получить {$embeddedKey} из amoCRM (HTTP {$response->status()})."
                    );
                }

                break;
            }

            $batch = $response->json("_embedded.{$embeddedKey}") ?? [];

            if (! is_array($batch) || $batch === []) {
                break;
            }

            $maxIdInBatch = 0;

            foreach ($batch as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $id = (int) ($row['id'] ?? 0);

                if ($id > $maxIdInBatch) {
                    $maxIdInBatch = $id;
                }
            }

            if ($afterId > 0 && $maxIdInBatch <= $afterId) {
                break;
            }

            foreach ($batch as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $mapped = $map($row);

                if (is_array($mapped)) {
                    $all[] = $mapped;
                }
            }

            if ($onPage !== null) {
                $onPage(count($all));
            }

            if (count($batch) < 250) {
                break;
            }

            if ($maxIdInBatch <= $afterId) {
                break;
            }

            $afterId = $maxIdInBatch;

            if (count($all) >= $maxItems) {
                $truncated = true;
                break;
            }

            $this->throttlePages();
        }

        return ['items' => $all, 'truncated' => $truncated];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function withIdCursor(array $query, int $afterId): array
    {
        $query['limit'] = 250;
        $query['order'] = ['id' => 'asc'];

        if ($afterId > 0) {
            $filter = is_array($query['filter'] ?? null) ? $query['filter'] : [];
            $idFilter = is_array($filter['id'] ?? null) ? $filter['id'] : [];
            $idFilter['from'] = $afterId + 1;
            $filter['id'] = $idFilter;
            $query['filter'] = $filter;
        }

        unset($query['page']);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function paginatedGet(string $path, array $query): Response
    {
        $maxAttempts = 6;
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = $this->request()
                    ->timeout(60)
                    ->connectTimeout(10)
                    ->get($path, $query);
            } catch (\Throwable $exception) {
                $lastException = $exception;

                if ($attempt >= $maxAttempts) {
                    throw $exception;
                }

                $this->throttle($attempt);

                continue;
            }

            if (in_array($response->status(), [429, 500, 502, 503, 504], true) && $attempt < $maxAttempts) {
                $this->throttle($attempt, $response);

                continue;
            }

            return $response;
        }

        if ($lastException instanceof \Throwable) {
            throw $lastException;
        }

        throw new AmoCrmException('Не удалось получить данные из amoCRM.');
    }

    private function throttle(int $attempt, ?Response $response = null): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $retryAfter = $response?->header('Retry-After');
        $seconds = is_numeric($retryAfter) ? max(1, (int) $retryAfter) : min(8, 2 ** max(0, $attempt - 1));

        usleep($seconds * 1_000_000);
    }

    private function throttlePages(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        // amoCRM: ~7 запросов/сек. Пауза между страницами, чтобы не словить 429 на середине выгрузки.
        usleep(150_000);
    }

    /**
     * @param  array<string, mixed>  $lead
     * @return array<string, mixed>
     */
    private function compactLead(array $lead): array
    {
        return [
            'id' => (int) ($lead['id'] ?? 0) ?: null,
            'name' => mb_substr((string) ($lead['name'] ?? ''), 0, 120),
            'price' => isset($lead['price']) ? (float) $lead['price'] : null,
            'pipeline_id' => (int) ($lead['pipeline_id'] ?? 0) ?: null,
            'status_id' => (int) ($lead['status_id'] ?? 0) ?: null,
            'responsible_user_id' => (int) ($lead['responsible_user_id'] ?? 0) ?: null,
            'created_at' => $lead['created_at'] ?? null,
            'updated_at' => $lead['updated_at'] ?? null,
            'closed_at' => $lead['closed_at'] ?? null,
            'loss_reason_id' => (int) ($lead['loss_reason_id'] ?? 0) ?: null,
        ];
    }

    /**
     * @param  array<string, mixed>  $task
     * @return array<string, mixed>
     */
    private function compactTask(array $task): array
    {
        return [
            'id' => (int) ($task['id'] ?? 0) ?: null,
            'text' => mb_substr((string) ($task['text'] ?? ''), 0, 160),
            'entity_type' => $task['entity_type'] ?? null,
            'entity_id' => (int) ($task['entity_id'] ?? 0) ?: null,
            'responsible_user_id' => (int) ($task['responsible_user_id'] ?? 0) ?: null,
            'complete_till' => $task['complete_till'] ?? null,
            'task_type_id' => (int) ($task['task_type_id'] ?? 0) ?: null,
        ];
    }

    /**
     * Сбор данных для снимка: быстрый параллельный core + полная пагинация списков.
     * Сделки, задачи и события сюда не входят — их выгружает полная пагинация
     * (getAllLeadsCompact / getAllOpenTasksCompact / getRecentEvents).
     * Секции с ok=false не должны затирать предыдущий успешный снимок.
     *
     * @return array{
     *     account: array{ok: bool, value: ?array, error: ?string},
     *     pipelines: array{ok: bool, value: list<array>, error: ?string},
     *     users: array{ok: bool, value: list<array>, error: ?string},
     *     groups: array{ok: bool, value: list<array>, error: ?string},
     *     widgets: array{ok: bool, value: list<array>, error: ?string},
     *     webhooks: array{ok: bool, value: list<array>, error: ?string},
     *     sources: array{ok: bool, value: list<array>, error: ?string},
     *     loss_reasons: array{ok: bool, value: list<array>, error: ?string},
     *     lead_tags: array{ok: bool, value: list<array>, error: ?string},
     *     contact_tags: array{ok: bool, value: list<array>, error: ?string},
     *     custom_fields: array{ok: bool, value: array<string, list<array>>, error: ?string},
     *     errors: list<string>
     * }
     */
    public function fetchSnapshotBundle(): array
    {
        $errors = [];
        $timeout = 25;

        $responses = Http::pool(function (Pool $pool) use ($timeout) {
            $base = fn (string $as) => $pool->as($as)
                ->baseUrl($this->account->baseUrl())
                ->withToken((string) $this->account->access_token)
                ->acceptJson()
                ->asJson()
                ->timeout($timeout)
                ->connectTimeout(8);

            return [
                $base('account')->get('/api/v4/account', [
                    'with' => 'amojo_id,uuid,version',
                ]),
                $base('pipelines')->get('/api/v4/leads/pipelines'),
            ];
        });

        $fromPool = function (string $key, ?string $embeddedKey = null) use ($responses): array {
            $response = $responses[$key] ?? null;

            if (! $response instanceof Response) {
                return ['ok' => false, 'value' => $embeddedKey === null ? null : [], 'error' => 'нет ответа'];
            }

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'value' => $embeddedKey === null ? null : [],
                    'error' => 'HTTP '.$response->status(),
                ];
            }

            if ($embeddedKey === null) {
                $json = $response->json();

                return ['ok' => true, 'value' => is_array($json) ? $json : null, 'error' => null];
            }

            $rows = $response->json('_embedded.'.$embeddedKey) ?? [];
            $list = is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];

            return ['ok' => true, 'value' => $list, 'error' => null];
        };

        $safe = function (callable $callback, string $label, mixed $empty = []): array {
            try {
                return ['ok' => true, 'value' => $callback(), 'error' => null];
            } catch (\Throwable $exception) {
                return ['ok' => false, 'value' => $empty, 'error' => $exception->getMessage()];
            }
        };

        $account = $fromPool('account');
        $pipelines = $fromPool('pipelines', 'pipelines');

        // Полная пагинация — иначе на больших аккаунтах счётчики «прыгают» (ровно 250 = обрезка 1-й страницы).
        $users = $safe(fn () => $this->paginateEmbedded('/api/v4/users', 'users', 20), 'users');
        $groups = $safe(fn () => $this->getUserGroups(), 'groups');
        $widgets = $safe(fn () => $this->paginateEmbedded('/api/v4/widgets', 'widgets', 20), 'widgets');
        $webhooks = $safe(fn () => $this->paginateEmbedded('/api/v4/webhooks', 'webhooks', 10), 'webhooks');
        $sources = $safe(fn () => $this->paginateEmbedded('/api/v4/sources', 'sources', 10), 'sources');
        $lossReasons = $safe(fn () => $this->paginateEmbedded('/api/v4/leads/loss_reasons', 'loss_reasons', 10), 'loss_reasons');
        $leadTags = $safe(fn () => $this->paginateEmbedded('/api/v4/leads/tags', 'tags', 10), 'lead_tags');
        $contactTags = $safe(fn () => $this->paginateEmbedded('/api/v4/contacts/tags', 'tags', 10), 'contact_tags');
        $customFields = $safe(function () {
            return [
                'leads' => $this->paginateEmbedded('/api/v4/leads/custom_fields', 'custom_fields', 20),
                'contacts' => $this->paginateEmbedded('/api/v4/contacts/custom_fields', 'custom_fields', 20),
                'companies' => $this->paginateEmbedded('/api/v4/companies/custom_fields', 'custom_fields', 20),
            ];
        }, 'custom_fields', ['leads' => [], 'contacts' => [], 'companies' => []]);

        foreach ([
            'account' => $account,
            'pipelines' => $pipelines,
            'users' => $users,
            'groups' => $groups,
            'widgets' => $widgets,
            'webhooks' => $webhooks,
            'sources' => $sources,
            'loss_reasons' => $lossReasons,
            'lead_tags' => $leadTags,
            'contact_tags' => $contactTags,
            'custom_fields' => $customFields,
        ] as $label => $section) {
            if (! ($section['ok'] ?? false)) {
                $errors[] = $label.': '.($section['error'] ?? 'ошибка');
            }
        }

        return [
            'account' => $account,
            'pipelines' => $pipelines,
            'users' => $users,
            'groups' => $groups,
            'widgets' => $widgets,
            'webhooks' => $webhooks,
            'sources' => $sources,
            'loss_reasons' => $lossReasons,
            'lead_tags' => $leadTags,
            'contact_tags' => $contactTags,
            'custom_fields' => $customFields,
            'errors' => $errors,
        ];
    }

    private function snapshotRequest(): PendingRequest
    {
        return $this->request()->timeout(15)->connectTimeout(5);
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatus(int $pipelineId, int $statusId): array
    {
        $response = $this->request()->get("/api/v4/leads/pipelines/{$pipelineId}/statuses/{$statusId}");
        $this->throwIfFailed($response, 'Не удалось получить этап воронки из amoCRM.');

        $data = $response->json() ?? [];

        return is_array($data) ? $data : [];
    }

    /**
     * Best-effort чтение настроек Digital Pipeline (официального API нет).
     *
     * @return array<string, mixed>|null
     */
    public function tryGetDigitalPipeline(int $pipelineId): ?array
    {
        $candidates = [
            ["/ajax/v4/leads/pipelines/{$pipelineId}/digital_pipeline", []],
            ["/ajax/v3/leads/pipelines/{$pipelineId}/triggers", []],
            ["/ajax/settings/pipeline/leads", ['pipeline_id' => $pipelineId]],
            ["/api/v2/pipelines", ['id' => $pipelineId]],
            ["/ajax/v1/pipelines/list", []],
        ];

        foreach ($candidates as [$path, $query]) {
            $json = $this->tryGet($path, $query);

            if ($json === null) {
                continue;
            }

            $encoded = mb_strtolower(json_encode($json, JSON_UNESCAPED_UNICODE) ?: '');

            if (
                str_contains($encoded, 'trigger')
                || str_contains($encoded, 'digital_pipeline')
                || str_contains($encoded, 'digitalpipeline')
                || str_contains($encoded, 'handlers')
                || (str_contains($encoded, 'action') && str_contains($encoded, 'event'))
            ) {
                return $json;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function paginateEmbedded(string $path, string $embeddedKey, int $maxPages = 8): array
    {
        $all = [];
        $page = 1;

        do {
            $response = $this->request()->timeout(25)->connectTimeout(8)->get($path, [
                'page' => $page,
                'limit' => 250,
            ]);

            if (! $response->successful()) {
                // Пустой массив на ошибке 1-й страницы нельзя считать успехом — затрёт прошлый снимок.
                if ($page === 1) {
                    throw new AmoCrmException(
                        "Не удалось получить {$embeddedKey} из amoCRM (HTTP {$response->status()})."
                    );
                }

                break;
            }

            $batch = $response->json("_embedded.{$embeddedKey}") ?? [];

            if (! is_array($batch) || $batch === []) {
                break;
            }

            foreach ($batch as $row) {
                if (is_array($row)) {
                    $all[] = $row;
                }
            }

            $page++;
        } while (count($batch) >= 250 && $page <= $maxPages);

        return $all;
    }

    public function pipelineExists(int $pipelineId): bool
    {
        $response = $this->request()->get("/api/v4/leads/pipelines/{$pipelineId}");

        if ($response->status() === 404) {
            return false;
        }

        $this->throwIfFailed($response, 'Не удалось проверить воронку в amoCRM.');

        return filled($response->json('id'));
    }

    /**
     * @return array<string, mixed>
     */
    public function getPipeline(int $pipelineId): array
    {
        $response = $this->request()->get("/api/v4/leads/pipelines/{$pipelineId}");
        $this->throwIfFailed($response, 'Не удалось получить воронку из amoCRM.');

        return $response->json() ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getPipelines(): array
    {
        $response = $this->request()->get('/api/v4/leads/pipelines');
        $this->throwIfFailed($response, 'Не удалось получить воронки из amoCRM.');

        return $response->json('_embedded.pipelines') ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getCustomFields(string $entityType): array
    {
        $all = [];
        $page = 1;

        do {
            $response = $this->request()->get("/api/v4/{$entityType}/custom_fields", [
                'page' => $page,
                'limit' => 250,
            ]);
            $this->throwIfFailed($response, 'Не удалось получить поля из amoCRM.');

            $batch = $response->json('_embedded.custom_fields') ?? [];

            if (! is_array($batch) || $batch === []) {
                break;
            }

            foreach ($batch as $field) {
                if (is_array($field)) {
                    $all[] = $field;
                }
            }

            $page++;
        } while (count($batch) >= 250 && $page <= 20);

        return $all;
    }

    /**
     * @param  list<array<string, mixed>>  $pipelines
     * @return list<array<string, mixed>>
     */
    public function createPipelines(array $pipelines): array
    {
        $response = $this->request()->post('/api/v4/leads/pipelines', $pipelines);
        $this->throwIfFailed($response, 'Не удалось создать воронки в amoCRM.');

        return $response->json('_embedded.pipelines') ?? [];
    }

    /**
     * @param  list<array<string, mixed>>  $statuses
     * @return list<array<string, mixed>>
     */
    public function createStatuses(int $pipelineId, array $statuses): array
    {
        $response = $this->request()->post(
            "/api/v4/leads/pipelines/{$pipelineId}/statuses",
            $statuses
        );
        $this->throwIfFailed($response, 'Не удалось создать этапы воронки в amoCRM.');

        return $response->json('_embedded.statuses') ?? [];
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return list<array<string, mixed>>
     */
    public function createCustomFields(string $entityType, array $fields): array
    {
        $response = $this->request()->post("/api/v4/{$entityType}/custom_fields", $fields);
        $this->throwIfFailed($response, 'Не удалось создать поля в amoCRM.');

        return $response->json('_embedded.custom_fields') ?? [];
    }

    /**
     * @param  list<array<string, mixed>>  $contacts
     * @return list<array<string, mixed>>
     */
    public function createContacts(array $contacts): array
    {
        if ($contacts === []) {
            return [];
        }

        $created = [];

        foreach (array_chunk($contacts, 50) as $chunk) {
            $response = $this->request()->post('/api/v4/contacts', $chunk);
            $this->throwIfFailed($response, 'Не удалось создать контакты в amoCRM.');

            $batch = $response->json('_embedded.contacts') ?? [];

            if (is_array($batch)) {
                foreach ($batch as $row) {
                    if (is_array($row)) {
                        $created[] = $row;
                    }
                }
            }
        }

        return $created;
    }

    /**
     * @param  list<array<string, mixed>>  $leads
     * @return list<array<string, mixed>>
     */
    public function createLeads(array $leads): array
    {
        if ($leads === []) {
            return [];
        }

        $created = [];

        foreach (array_chunk($leads, 50) as $chunk) {
            $response = $this->request()->post('/api/v4/leads', $chunk);
            $this->throwIfFailed($response, 'Не удалось создать сделки в amoCRM.');

            $batch = $response->json('_embedded.leads') ?? [];

            if (is_array($batch)) {
                foreach ($batch as $row) {
                    if (is_array($row)) {
                        $created[] = $row;
                    }
                }
            }
        }

        return $created;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getContacts(int $limit = 250, int $page = 1): array
    {
        $limit = min(250, max(1, $limit));
        $response = $this->request()->get('/api/v4/contacts', [
            'limit' => $limit,
            'page' => max(1, $page),
            'order' => ['updated_at' => 'desc'],
        ]);

        if (! $response->successful()) {
            $this->throwIfFailed($response, 'Не удалось получить контакты из amoCRM.');
        }

        $rows = $response->json('_embedded.contacts') ?? [];

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public function getLeads(array $query = []): array
    {
        $response = $this->request()->get('/api/v4/leads', $query);

        if (! $response->successful()) {
            $this->throwIfFailed($response, 'Не удалось получить сделки из amoCRM.');
        }

        $rows = $response->json('_embedded.leads') ?? [];

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /**
     * Сделки на конкретном этапе (с контактами, если with=contacts).
     *
     * @return list<array<string, mixed>>
     */
    public function getLeadsByStatus(int $pipelineId, int $statusId, int $limit = 250, bool $withContacts = true): array
    {
        $limit = min(250, max(1, $limit));
        $query = [
            'limit' => $limit,
            'page' => 1,
            'filter' => [
                'statuses' => [[
                    'pipeline_id' => $pipelineId,
                    'status_id' => $statusId,
                ]],
            ],
        ];

        if ($withContacts) {
            $query['with'] = 'contacts';
        }

        return $this->getLeads($query);
    }

    /**
     * @param  list<int>  $contactIds
     */
    public function linkLeadToContacts(int $leadId, array $contactIds): void
    {
        $contactIds = array_values(array_unique(array_filter(
            array_map('intval', $contactIds),
            fn (int $id) => $id > 0
        )));

        if ($contactIds === []) {
            return;
        }

        $payload = array_map(
            fn (int $id) => [
                'to_entity_id' => $id,
                'to_entity_type' => 'contacts',
            ],
            $contactIds
        );

        $response = $this->request()->post("/api/v4/leads/{$leadId}/link", $payload);
        $this->throwIfFailed($response, 'Не удалось прикрепить контакты к сделке в amoCRM.');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateStatus(int $pipelineId, int $statusId, array $payload): array
    {
        $response = $this->request()->patch(
            "/api/v4/leads/pipelines/{$pipelineId}/statuses/{$statusId}",
            $payload
        );
        $this->throwIfFailed($response, 'Не удалось обновить подсказку этапа в amoCRM.');

        return $response->json() ?? [];
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->account->baseUrl())
            ->withToken($this->account->access_token)
            ->acceptJson()
            ->asJson()
            ->timeout(60);
    }

    private function throwIfFailed(Response $response, string $fallback): void
    {
        if ($response->successful()) {
            return;
        }

        $detail = $response->json('detail')
            ?? $response->json('title')
            ?? $response->json('validation-errors.0.errors.0.detail')
            ?? $response->json('validation-errors.0.detail');

        if (! is_string($detail) || $detail === '') {
            $validation = $response->json('validation-errors');

            if (is_array($validation) && $validation !== []) {
                $detail = json_encode($validation, JSON_UNESCAPED_UNICODE);
            } else {
                $detail = $response->body();
            }
        }

        $message = is_string($detail) && $detail !== ''
            ? $detail
            : $fallback;

        if ($response->status() === 401) {
            $message = 'Токен amoCRM недействителен или истёк. Проверьте subdomain и access token.';
        }

        throw new AmoCrmException($message.' (HTTP '.$response->status().')', $response->status());
    }
}
