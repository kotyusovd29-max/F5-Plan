<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectAmocrmAuditExport;
use App\Models\User;
use App\Services\AmoCrmAccountSnapshotService;
use App\Services\F5AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AmoCrmAuditExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') === 'mysql') {
            $this->markTestSkipped('RefreshDatabase must not run against the development MySQL database.');
        }

        parent::setUp();
    }

    private function makeProjectWithAccount(User $user): Project
    {
        $project = Project::query()->create([
            'user_id' => $user->id,
            'company_name' => 'ООО Аудит',
        ]);

        $project->amocrmAccount()->create([
            'subdomain' => 'demo',
            'access_token' => 'token-123',
            'account_id' => 77,
            'account_name' => 'Demo',
        ]);

        return $project->fresh(['amocrmAccount']);
    }

    /**
     * Полная пагинация: 2 страницы сделок (250 + 3), 2 открытые задачи, события.
     */
    private function fakeAmoAccount(): void
    {
        Http::fake([
            'https://demo.amocrm.ru/api/v4/account*' => Http::response([
                'id' => 77,
                'name' => 'Demo',
                'subdomain' => 'demo',
            ]),
            'https://demo.amocrm.ru/api/v4/leads/pipelines' => Http::response([
                '_embedded' => [
                    'pipelines' => [[
                        'id' => 10,
                        'name' => 'Продажи',
                        'is_main' => true,
                        '_embedded' => [
                            'statuses' => [
                                ['id' => 101, 'name' => 'Новая', 'sort' => 10, 'type' => 0],
                                ['id' => 102, 'name' => 'Квалификация', 'sort' => 20, 'type' => 0],
                            ],
                        ],
                    ]],
                ],
            ]),
            'https://demo.amocrm.ru/api/v4/leads/loss_reasons*' => Http::response([
                '_embedded' => ['loss_reasons' => [['id' => 5, 'name' => 'Дорого']]],
            ]),
            'https://demo.amocrm.ru/api/v4/leads*' => function ($request) {
                $path = (string) parse_url($request->url(), PHP_URL_PATH);

                if ($path !== '/api/v4/leads') {
                    return Http::response(['_embedded' => []], 200);
                }

                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                $from = (int) data_get($query, 'filter.id.from', 0);

                if ($from >= 251) {
                    return Http::response([
                        '_embedded' => [
                            'leads' => [
                                ['id' => 251, 'name' => 'Сделка 251', 'price' => 5000, 'pipeline_id' => 10, 'status_id' => 102, 'responsible_user_id' => 1, 'created_at' => time(), 'updated_at' => time()],
                                ['id' => 252, 'name' => 'Сделка 252', 'price' => 6000, 'pipeline_id' => 10, 'status_id' => 102, 'responsible_user_id' => 1, 'created_at' => time(), 'updated_at' => time(), 'closed_at' => time(), 'loss_reason_id' => 5],
                                ['id' => 253, 'name' => 'Сделка 253', 'price' => 7000, 'pipeline_id' => 10, 'status_id' => 101, 'responsible_user_id' => 1, 'created_at' => time(), 'updated_at' => time()],
                            ],
                        ],
                    ]);
                }

                $leads = [];

                for ($i = 1; $i <= 250; $i++) {
                    $leads[] = [
                        'id' => $i,
                        'name' => 'Сделка '.$i,
                        'price' => 1000,
                        'pipeline_id' => 10,
                        'status_id' => 101,
                        'responsible_user_id' => 1,
                        'created_at' => time(),
                        'updated_at' => time(),
                    ];
                }

                return Http::response(['_embedded' => ['leads' => $leads]]);
            },
            'https://demo.amocrm.ru/api/v4/tasks*' => function ($request) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                $from = (int) data_get($query, 'filter.id.from', 0);

                if ($from >= 251) {
                    return Http::response([
                        '_embedded' => [
                            'tasks' => [
                                ['id' => 251, 'text' => 'Дожать КП', 'entity_type' => 'leads', 'entity_id' => 51, 'responsible_user_id' => 1, 'complete_till' => time() + 3600, 'task_type_id' => 2],
                                ['id' => 252, 'text' => 'Согласовать договор', 'entity_type' => 'leads', 'entity_id' => 52, 'responsible_user_id' => 1, 'complete_till' => time() + 7200, 'task_type_id' => 2],
                            ],
                        ],
                    ]);
                }

                $tasks = [];

                for ($i = 1; $i <= 250; $i++) {
                    $tasks[] = [
                        'id' => $i,
                        'text' => 'Задача '.$i,
                        'entity_type' => 'leads',
                        'entity_id' => $i,
                        'responsible_user_id' => 1,
                        'complete_till' => $i === 1 ? time() - 3600 : time() + 3600,
                        'task_type_id' => 1,
                    ];
                }

                return Http::response(['_embedded' => ['tasks' => $tasks]]);
            },
            'https://demo.amocrm.ru/api/v4/events*' => Http::response([
                '_embedded' => [
                    'events' => [
                        ['id' => 1, 'type' => 'lead_status_changed', 'entity_id' => 1, 'created_at' => time()],
                    ],
                ],
            ]),
            'https://demo.amocrm.ru/api/v4/leads/loss_reasons*' => Http::response([
                '_embedded' => ['loss_reasons' => [['id' => 5, 'name' => 'Дорого']]],
            ]),
            'https://demo.amocrm.ru/api/v4/*' => Http::response(['_embedded' => []], 200),
        ]);
    }

    public function test_sync_stores_full_export_in_database_with_one_day_ttl(): void
    {
        $user = User::factory()->create();
        $project = $this->makeProjectWithAccount($user);
        $this->fakeAmoAccount();

        $payload = app(AmoCrmAccountSnapshotService::class)->sync($project);

        // Полная выгрузка: 253 сделки (2 страницы), а не 250/первая страница.
        $this->assertCount(253, $payload['leads']);
        $this->assertSame(253, $payload['leads_analytics']['total']);
        $this->assertSame(1, $payload['leads_analytics']['closed']);
        $this->assertSame(5, $payload['leads_analytics']['by_loss_reason'][0]['loss_reason_id'] ?? null);
        $this->assertSame('Дорого', $payload['leads_analytics']['by_loss_reason'][0]['name'] ?? null);
        $this->assertCount(252, $payload['tasks_open']);
        $this->assertSame(252, $payload['tasks_analytics']['total_open']);
        $this->assertSame(1, $payload['tasks_analytics']['overdue']);

        $export = $project->amocrmAuditExports()->first();
        $this->assertNotNull($export);
        $this->assertCount(253, $export->payload['leads']);
        $this->assertFalse($export->isExpired());
        $this->assertTrue($export->expires_at->greaterThan(now()->addHours(23)));
        $this->assertTrue($export->expires_at->lessThan(now()->addHours(25)));

        // На аккаунте остаётся компактный снимок без сырых списков сделок.
        $snapshot = $project->fresh()->amocrmAccount->account_snapshot;
        $this->assertArrayNotHasKey('leads', $snapshot);
        $this->assertSame(253, $snapshot['summary']['leads_total']);
        $this->assertSame(252, $snapshot['summary']['tasks_open_total']);
        $this->assertSame(1, $snapshot['summary']['tasks_overdue']);
    }

    public function test_sync_if_stale_reuses_fresh_export_without_new_requests(): void
    {
        $user = User::factory()->create();
        $project = $this->makeProjectWithAccount($user);
        $this->fakeAmoAccount();

        $service = app(AmoCrmAccountSnapshotService::class);
        $service->sync($project);

        $requestsAfterSync = count(Http::recorded());

        $fresh = $service->syncIfStale($project->fresh(['amocrmAccount']), 20);

        $this->assertSame(1, ProjectAmocrmAuditExport::query()->count());
        $this->assertSame(253, $fresh['leads_analytics']['total'] ?? null);
        // Новых HTTP-запросов к amoCRM не было.
        $this->assertCount($requestsAfterSync, Http::recorded());
    }

    public function test_expired_exports_are_pruned_and_project_is_kept(): void
    {
        $user = User::factory()->create();
        $project = $this->makeProjectWithAccount($user);

        ProjectAmocrmAuditExport::query()->create([
            'project_id' => $project->id,
            'payload' => ['leads' => [['id' => 1]]],
            'fetched_at' => now()->subDays(2),
            'expires_at' => now()->subDay(),
        ]);

        ProjectAmocrmAuditExport::query()->create([
            'project_id' => $project->id,
            'payload' => ['leads' => [['id' => 2]]],
            'fetched_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

        $this->artisan('model:prune', ['--model' => ProjectAmocrmAuditExport::class]);

        $this->assertSame(1, ProjectAmocrmAuditExport::query()->count());
        $this->assertSame(2, ProjectAmocrmAuditExport::query()->first()->payload['leads'][0]['id'] ?? null);

        // Сам проект и подключение amoCRM не затронуты.
        $this->assertNotNull($project->fresh());
        $this->assertNotNull($project->fresh()->amocrmAccount);
    }

    public function test_new_sync_replaces_previous_exports(): void
    {
        $user = User::factory()->create();
        $project = $this->makeProjectWithAccount($user);
        $this->fakeAmoAccount();

        $service = app(AmoCrmAccountSnapshotService::class);
        $service->sync($project);
        $service->sync($project->fresh(['amocrmAccount']));

        $this->assertSame(1, ProjectAmocrmAuditExport::query()->count());
    }

    public function test_audit_stream_sends_sse_chunks_and_saves_result(): void
    {
        config(['f5ai.api_key' => 'test-key']);

        $user = User::factory()->create();
        $project = $this->makeProjectWithAccount($user);

        Http::fake([
            'https://api.f5ai.ru/*' => Http::response(
                "data: {\"choices\":[{\"delta\":{\"content\":\"Аудит \"}}]}\n"
                ."data: {\"choices\":[{\"delta\":{\"content\":\"готов. [КОНЕЦ АУДИТА]\"}}]}\n"
                ."data: [DONE]\n\n",
                200,
                ['Content-Type' => 'text/event-stream']
            ),
            'https://demo.amocrm.ru/api/v4/account*' => Http::response([
                'id' => 77,
                'name' => 'Demo',
                'subdomain' => 'demo',
            ]),
            'https://demo.amocrm.ru/api/v4/leads/pipelines' => Http::response([
                '_embedded' => [
                    'pipelines' => [[
                        'id' => 10,
                        'name' => 'Продажи',
                        '_embedded' => ['statuses' => [['id' => 101, 'name' => 'Новая', 'type' => 0]]],
                    ]],
                ],
            ]),
            'https://demo.amocrm.ru/api/v4/*' => Http::response(['_embedded' => []], 200),
        ]);

        $response = $this->actingAs($user)->postJson(
            route('projects.amocrm.audit.stream', $project),
            ['pipeline_ids' => [10], 'include_fields' => true]
        );

        $response->assertOk();
        $this->assertStringContainsString('text/event-stream', (string) $response->headers->get('Content-Type'));

        $content = $response->streamedContent();

        $this->assertStringContainsString('event: status', $content);
        $this->assertStringContainsString('event: chunk', $content);
        $this->assertStringContainsString('Аудит ', $content);
        $this->assertStringContainsString('event: done', $content);

        $account = $project->fresh()->amocrmAccount;
        $this->assertSame('Аудит готов.', trim((string) $account->last_ai_audit_result));
        $this->assertNotNull($account->last_ai_audit_at);

        // Аудит сохранён в ИИ-чате проекта.
        $this->assertSame(2, $project->aiMessages()->count());
    }

    public function test_streamed_completion_falls_back_to_plain_json(): void
    {
        config(['f5ai.api_key' => 'test-key']);

        Http::fake([
            'https://api.f5ai.ru/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Полный ответ']],
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $deltas = [];
        $content = app(F5AiService::class)->chatCompletionStreamed(
            [['role' => 'user', 'content' => 'Привет']],
            ['timeout' => 0],
            function (string $delta) use (&$deltas) {
                $deltas[] = $delta;
            }
        );

        $this->assertSame('Полный ответ', $content);
        $this->assertSame(['Полный ответ'], $deltas);
    }

    public function test_lead_pagination_retries_rate_limit_then_loads_next_page(): void
    {
        $user = User::factory()->create();
        $project = $this->makeProjectWithAccount($user);
        $firstPageAttempts = 0;

        Http::fake([
            'https://demo.amocrm.ru/api/v4/leads*' => function ($request) use (&$firstPageAttempts) {
                $path = (string) parse_url($request->url(), PHP_URL_PATH);

                if ($path !== '/api/v4/leads') {
                    return Http::response(['_embedded' => []], 200);
                }

                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                $from = (int) data_get($query, 'filter.id.from', 0);

                if ($from === 0) {
                    $firstPageAttempts++;

                    if ($firstPageAttempts === 1) {
                        return Http::response(['detail' => 'Too Many Requests'], 429);
                    }

                    $leads = [];

                    for ($i = 1; $i <= 250; $i++) {
                        $leads[] = [
                            'id' => $i,
                            'name' => 'Сделка '.$i,
                            'pipeline_id' => 10,
                            'status_id' => 101,
                            'created_at' => time(),
                        ];
                    }

                    return Http::response(['_embedded' => ['leads' => $leads]]);
                }

                return Http::response([
                    '_embedded' => [
                        'leads' => [
                            ['id' => 251, 'name' => 'Сделка 251', 'pipeline_id' => 10, 'status_id' => 101, 'created_at' => time()],
                        ],
                    ],
                ]);
            },
            'https://demo.amocrm.ru/api/v4/*' => Http::response(['_embedded' => []], 200),
        ]);

        $payload = app(AmoCrmAccountSnapshotService::class)->sync($project);

        $this->assertSame(2, $firstPageAttempts);
        $this->assertCount(251, $payload['leads']);
        $this->assertSame(251, $payload['leads_analytics']['total']);
    }
}
