<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AmoCrmAccountSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') === 'mysql') {
            $this->markTestSkipped('RefreshDatabase must not run against the development MySQL database.');
        }

        parent::setUp();
    }

    public function test_connect_stores_account_snapshot(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'company_name' => 'ООО Снимок',
        ]);

        Http::fake([
            'https://demo.amocrm.ru/api/v4/account*' => Http::response([
                'id' => 77,
                'name' => 'Demo Snap',
                'subdomain' => 'demo',
            ]),
            'https://demo.amocrm.ru/api/v4/users*' => Http::response([
                '_embedded' => [
                    'users' => [
                        ['id' => 1, 'name' => 'Менеджер', 'email' => 'm@example.com'],
                    ],
                ],
            ]),
            'https://demo.amocrm.ru/api/v4/leads/pipelines' => Http::response([
                '_embedded' => [
                    'pipelines' => [[
                        'id' => 10,
                        'name' => 'Продажи',
                        'is_main' => true,
                        '_embedded' => [
                            'statuses' => [
                                [
                                    'id' => 101,
                                    'name' => 'Новая',
                                    'sort' => 10,
                                    'type' => 0,
                                    'descriptions' => [
                                        ['level' => 'green', 'description' => 'Позвонить за 15 минут'],
                                    ],
                                ],
                            ],
                        ],
                    ]],
                ],
            ]),
            'https://demo.amocrm.ru/api/v4/leads/pipelines/*/statuses/*' => Http::response([
                'id' => 101,
                'name' => 'Новая',
                'descriptions' => [
                    ['level' => 'green', 'description' => 'Позвонить за 15 минут'],
                ],
            ]),
            'https://demo.amocrm.ru/api/v4/leads/custom_fields*' => Http::response([
                '_embedded' => ['custom_fields' => []],
            ]),
            'https://demo.amocrm.ru/api/v4/contacts/custom_fields*' => Http::response([
                '_embedded' => ['custom_fields' => []],
            ]),
            'https://demo.amocrm.ru/api/v4/companies/custom_fields*' => Http::response([
                '_embedded' => ['custom_fields' => []],
            ]),
            'https://demo.amocrm.ru/api/v4/leads/loss_reasons*' => Http::response([
                '_embedded' => ['loss_reasons' => []],
            ]),
            'https://demo.amocrm.ru/api/v4/leads/tags*' => Http::response([
                '_embedded' => ['tags' => []],
            ]),
            'https://demo.amocrm.ru/api/v4/leads*' => function ($request) {
                $path = (string) parse_url($request->url(), PHP_URL_PATH);

                if ($path === '/api/v4/leads') {
                    return Http::response([
                        '_embedded' => [
                            'leads' => [
                                [
                                    'id' => 501,
                                    'name' => 'Сделка 1',
                                    'price' => 15000,
                                    'pipeline_id' => 10,
                                    'status_id' => 101,
                                    'responsible_user_id' => 1,
                                    'created_at' => time(),
                                    'updated_at' => time(),
                                ],
                            ],
                        ],
                    ]);
                }

                return Http::response(['_embedded' => []], 200);
            },
            'https://demo.amocrm.ru/api/v4/widgets*' => Http::response([
                '_embedded' => [
                    'widgets' => [
                        ['code' => 'chatter', 'name' => 'Chatter'],
                    ],
                ],
            ]),
            'https://demo.amocrm.ru/api/v4/*' => Http::response(['_embedded' => []], 200),
        ]);

        $this->actingAs($user)
            ->post(route('projects.amocrm.connect', $project), [
                'subdomain' => 'demo',
                'access_token' => 'token-123',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $account = $project->fresh()->amocrmAccount;
        $this->assertNotNull($account);
        $this->assertNotNull($account->account_snapshot_at);
        $this->assertIsArray($account->account_snapshot);
        $this->assertSame(1, $account->account_snapshot['summary']['pipelines'] ?? null);
        $this->assertSame(1, $account->account_snapshot['summary']['leads_in_sample'] ?? null);
        $this->assertSame(1, $account->account_snapshot['summary']['users'] ?? null);
        $this->assertGreaterThanOrEqual(1, $account->account_snapshot['summary']['statuses_with_tips'] ?? 0);
        $this->assertSame(501, $account->account_snapshot['leads_analytics']['recent_leads'][0]['id'] ?? null);
    }

    public function test_refresh_snapshot_endpoint(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'company_name' => 'ООО Refresh',
        ]);
        $project->amocrmAccount()->create([
            'subdomain' => 'demo',
            'access_token' => 'token-123',
            'account_id' => 77,
            'account_name' => 'Demo',
        ]);

        Http::fake([
            'https://demo.amocrm.ru/api/v4/account*' => Http::response([
                'id' => 77,
                'name' => 'Demo',
                'subdomain' => 'demo',
            ]),
            'https://demo.amocrm.ru/api/v4/leads/pipelines' => Http::response([
                '_embedded' => ['pipelines' => []],
            ]),
            'https://demo.amocrm.ru/api/v4/*' => Http::response(['_embedded' => []], 200),
            'https://demo.amocrm.ru/ajax/*' => Http::response([], 404),
        ]);

        $this->actingAs($user)
            ->postJson(route('projects.amocrm.snapshot', $project))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['summary', 'snapshot_at']);
    }

    public function test_refresh_keeps_previous_section_when_request_fails(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'company_name' => 'ООО Stable',
        ]);
        $project->amocrmAccount()->create([
            'subdomain' => 'demo',
            'access_token' => 'token-123',
            'account_id' => 77,
            'account_name' => 'Demo',
            'account_snapshot_at' => now()->subMinute(),
            'account_snapshot' => [
                'users' => [
                    ['id' => 1, 'name' => 'Старый пользователь'],
                    ['id' => 2, 'name' => 'Второй'],
                ],
                'widgets' => array_fill(0, 12, ['code' => 'w']),
                'pipelines' => [[
                    'id' => 10,
                    'name' => 'Продажи',
                    'statuses' => [['id' => 101, 'name' => 'Новая', 'descriptions' => []]],
                ]],
                'webhooks' => [],
                'sources' => [],
                'loss_reasons' => [],
                'groups' => [],
                'tags' => ['leads' => [], 'contacts' => []],
                'custom_fields' => ['leads' => [], 'contacts' => [], 'companies' => []],
                'events_recent' => [],
                'tasks_open_sample' => [],
                'leads_analytics' => ['sample_size' => 3, 'by_status' => [], 'recent_leads' => []],
                'digital_pipeline' => ['available' => false, 'pipelines' => []],
                'summary' => ['users' => 2, 'widgets' => 12, 'pipelines' => 1],
                'errors' => [],
            ],
        ]);

        Http::fake([
            'https://demo.amocrm.ru/api/v4/account*' => Http::response([
                'id' => 77,
                'name' => 'Demo',
                'subdomain' => 'demo',
            ]),
            'https://demo.amocrm.ru/api/v4/leads/pipelines' => Http::response([
                '_embedded' => ['pipelines' => [[
                    'id' => 10,
                    'name' => 'Продажи',
                    '_embedded' => ['statuses' => [['id' => 101, 'name' => 'Новая']]],
                ]]],
            ]),
            'https://demo.amocrm.ru/api/v4/users*' => Http::response(['message' => 'timeout'], 504),
            'https://demo.amocrm.ru/api/v4/widgets*' => Http::response(['message' => 'timeout'], 504),
            'https://demo.amocrm.ru/api/v4/leads*' => function ($request) {
                $path = (string) parse_url($request->url(), PHP_URL_PATH);

                if ($path === '/api/v4/leads') {
                    return Http::response([
                        '_embedded' => [
                            'leads' => [[
                                'id' => 9,
                                'name' => 'Сделка',
                                'pipeline_id' => 10,
                                'status_id' => 101,
                                'created_at' => time(),
                            ]],
                        ],
                    ]);
                }

                return Http::response(['_embedded' => []], 200);
            },
            'https://demo.amocrm.ru/api/v4/*' => Http::response(['_embedded' => []], 200),
        ]);

        $this->actingAs($user)
            ->postJson(route('projects.amocrm.snapshot', $project))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.users', 2)
            ->assertJsonPath('summary.widgets', 12)
            ->assertJsonPath('summary.is_partial', true);

        $snapshot = $project->fresh()->amocrmAccount->account_snapshot;
        $this->assertContains('users', $snapshot['reused_sections'] ?? []);
        $this->assertContains('widgets', $snapshot['reused_sections'] ?? []);
    }
}
