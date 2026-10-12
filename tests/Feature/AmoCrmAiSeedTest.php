<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AmoCrmAiSeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') === 'mysql') {
            $this->markTestSkipped('RefreshDatabase must not run against the development MySQL database.');
        }

        parent::setUp();
    }

    public function test_ai_seed_preview_and_apply_create_contacts(): void
    {
        config([
            'f5ai.api_key' => 'test-key',
            'f5ai.api_url' => 'https://api.f5ai.test/v2',
            'f5ai.model' => 'gpt-test',
            'f5ai.max_tokens' => 0,
        ]);

        [$user, $project] = $this->makeConnectedProject('ООО Seed');

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();
            $method = $request->method();

            if (str_contains($url, 'api.f5ai.test')) {
                return Http::response([
                    'choices' => [[
                        'message' => [
                            'content' => json_encode([
                                'message' => 'Подготовил 2 тестовых контакта',
                                'intent' => 'create_contacts',
                                'contacts' => [
                                    [
                                        'name' => 'Иван Петров',
                                        'phone' => '+79001112233',
                                        'email' => 'ivan@example.com',
                                        'fields' => ['Дата рождения' => '1990-05-12'],
                                    ],
                                    [
                                        'name' => 'Мария Сидорова',
                                        'phone' => '+79004445566',
                                        'email' => 'maria@example.com',
                                        'fields' => ['Дата рождения' => '1988-01-20'],
                                    ],
                                ],
                                'leads' => [],
                            ], JSON_UNESCAPED_UNICODE),
                        ],
                    ]],
                ]);
            }

            if ($catalog = $this->matchAmocrmCatalog($url)) {
                return $catalog;
            }

            if (str_contains($url, '/api/v4/contacts') && $method === 'GET') {
                return Http::response([
                    '_embedded' => [
                        'contacts' => [
                            ['id' => 11, 'name' => 'Существующий'],
                        ],
                    ],
                ]);
            }

            if (str_contains($url, '/api/v4/contacts') && $method === 'POST') {
                return Http::response([
                    '_embedded' => [
                        'contacts' => [
                            ['id' => 501, 'name' => '[F5 demo] Иван Петров'],
                            ['id' => 502, 'name' => '[F5 demo] Мария Сидорова'],
                        ],
                    ],
                ]);
            }

            return Http::response(['message' => 'unexpected '.$method.' '.$url], 500);
        });

        $preview = $this->actingAs($user)->postJson(route('projects.amocrm.ai-seed.preview', $project), [
            'prompt' => 'Создай 2 контакта с датой рождения',
        ]);

        $preview->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.contacts', 2)
            ->assertJsonPath('contacts.0.name', '[F5 demo] Иван Петров');

        $payload = $preview->json('payload');
        $this->assertIsArray($payload['contacts'] ?? null);
        $this->assertNotEmpty($payload['contacts'][0]['custom_fields_values'] ?? []);

        $apply = $this->actingAs($user)->postJson(route('projects.amocrm.ai-seed', $project), $payload);

        $apply->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.contacts_created', 2);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/api/v4/contacts') || $request->method() !== 'POST') {
                return false;
            }

            $body = $request->data();
            $first = $body[0] ?? null;

            return is_array($first)
                && str_contains((string) ($first['name'] ?? ''), '[F5 demo]')
                && ! array_key_exists('_preview', $first)
                && is_array($first['custom_fields_values'] ?? null);
        });
    }

    public function test_ai_seed_preview_create_leads_with_existing_contacts(): void
    {
        config([
            'f5ai.api_key' => 'test-key',
            'f5ai.api_url' => 'https://api.f5ai.test/v2',
            'f5ai.model' => 'gpt-test',
            'f5ai.max_tokens' => 0,
        ]);

        [$user, $project] = $this->makeConnectedProject('ООО Leads');

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();
            $method = $request->method();

            if (str_contains($url, 'api.f5ai.test')) {
                return Http::response([
                    'choices' => [[
                        'message' => [
                            'content' => json_encode([
                                'message' => 'Создам 2 сделки с контактами из amo',
                                'intent' => 'create_leads',
                                'contacts' => [],
                                'create_leads' => [
                                    'count' => 2,
                                    'pipeline' => 'Продажа украшений',
                                    'status' => 'Новая заявка',
                                    'attach_existing_contacts' => true,
                                    'leads' => [
                                        ['name' => 'Сделка А', 'price' => 1000],
                                        ['name' => 'Сделка Б', 'price' => 2000],
                                    ],
                                ],
                            ], JSON_UNESCAPED_UNICODE),
                        ],
                    ]],
                ]);
            }

            if ($catalog = $this->matchAmocrmCatalog($url)) {
                return $catalog;
            }

            if (str_contains($url, '/api/v4/contacts') && $method === 'GET') {
                return Http::response([
                    '_embedded' => [
                        'contacts' => [
                            ['id' => 11, 'name' => 'Анна'],
                            ['id' => 12, 'name' => 'Борис'],
                        ],
                    ],
                ]);
            }

            if (str_contains($url, '/api/v4/leads') && $method === 'POST' && ! str_contains($url, '/link')) {
                return Http::response([
                    '_embedded' => [
                        'leads' => [
                            ['id' => 901, 'name' => '[F5 demo] Сделка А'],
                            ['id' => 902, 'name' => '[F5 demo] Сделка Б'],
                        ],
                    ],
                ]);
            }

            return Http::response(['message' => 'unexpected '.$method.' '.$url], 500);
        });

        $preview = $this->actingAs($user)->postJson(route('projects.amocrm.ai-seed.preview', $project), [
            'prompt' => 'Создай 2 сделки в воронке Продажа украшений на этапе Новая заявка и прикрепи контакты из amoCRM',
        ]);

        $preview->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.leads', 2)
            ->assertJsonPath('summary.contacts', 0)
            ->assertJsonPath('leads.0.pipeline', 'Продажа украшений')
            ->assertJsonPath('leads.0.status', 'Новая заявка')
            ->assertJsonPath('leads.0.contact_name', 'Анна');

        $payload = $preview->json('payload');
        $this->assertSame(10, $payload['leads'][0]['pipeline_id'] ?? null);
        $this->assertSame(101, $payload['leads'][0]['status_id'] ?? null);
        $this->assertSame(11, $payload['leads'][0]['_embedded']['contacts'][0]['id'] ?? null);

        $apply = $this->actingAs($user)->postJson(route('projects.amocrm.ai-seed', $project), $payload);

        $apply->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.leads_created', 2);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/api/v4/leads') || $request->method() !== 'POST' || str_contains($request->url(), '/link')) {
                return false;
            }

            $body = $request->data();
            $first = $body[0] ?? null;

            return is_array($first)
                && ($first['pipeline_id'] ?? null) === 10
                && ($first['status_id'] ?? null) === 101
                && ($first['_embedded']['contacts'][0]['id'] ?? null) === 11
                && ! array_key_exists('_preview', $first);
        });
    }

    public function test_ai_seed_preview_link_contacts_to_leads_without_contacts(): void
    {
        config([
            'f5ai.api_key' => 'test-key',
            'f5ai.api_url' => 'https://api.f5ai.test/v2',
            'f5ai.model' => 'gpt-test',
            'f5ai.max_tokens' => 0,
        ]);

        [$user, $project] = $this->makeConnectedProject('ООО Links');

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();
            $method = $request->method();

            if (str_contains($url, 'api.f5ai.test')) {
                return Http::response([
                    'choices' => [[
                        'message' => [
                            'content' => json_encode([
                                'message' => 'Прикреплю контакты к сделкам без них',
                                'intent' => 'link_contacts',
                                'contacts' => [],
                                'link_contacts' => [
                                    'pipeline' => 'Продажа украшений',
                                    'status' => 'Новая заявка',
                                    'only_without_contacts' => true,
                                    'max' => 50,
                                ],
                            ], JSON_UNESCAPED_UNICODE),
                        ],
                    ]],
                ]);
            }

            if ($catalog = $this->matchAmocrmCatalog($url)) {
                return $catalog;
            }

            if (str_contains($url, '/api/v4/contacts') && $method === 'GET') {
                return Http::response([
                    '_embedded' => [
                        'contacts' => [
                            ['id' => 11, 'name' => 'Анна'],
                            ['id' => 12, 'name' => 'Борис'],
                        ],
                    ],
                ]);
            }

            if (str_contains($url, '/api/v4/leads') && $method === 'GET') {
                return Http::response([
                    '_embedded' => [
                        'leads' => [
                            [
                                'id' => 701,
                                'name' => 'Без контакта 1',
                                '_embedded' => ['contacts' => []],
                            ],
                            [
                                'id' => 702,
                                'name' => 'С контактом',
                                '_embedded' => ['contacts' => [['id' => 99]]],
                            ],
                            [
                                'id' => 703,
                                'name' => 'Без контакта 2',
                                '_embedded' => ['contacts' => []],
                            ],
                        ],
                    ],
                ]);
            }

            if (str_contains($url, '/link') && $method === 'POST') {
                return Http::response([], 200);
            }

            return Http::response(['message' => 'unexpected '.$method.' '.$url], 500);
        });

        $preview = $this->actingAs($user)->postJson(route('projects.amocrm.ai-seed.preview', $project), [
            'prompt' => 'Есть сделки на этапе Новая заявка. Во все сделки без контактов прикрепи контакты из amoCRM',
        ]);

        $preview->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.links', 2)
            ->assertJsonPath('summary.leads', 0)
            ->assertJsonPath('links.0.lead_id', 701)
            ->assertJsonPath('links.0.contact_id', 11)
            ->assertJsonPath('links.1.lead_id', 703)
            ->assertJsonPath('links.1.contact_id', 12);

        $payload = $preview->json('payload');

        $apply = $this->actingAs($user)->postJson(route('projects.amocrm.ai-seed', $project), $payload);

        $apply->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.links_created', 2);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/leads/701/link')
            && $request->method() === 'POST');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/leads/703/link')
            && $request->method() === 'POST');
    }

    public function test_ai_seed_apply_requires_payload(): void
    {
        [$user, $project] = $this->makeConnectedProject('ООО Empty');

        $this->actingAs($user)
            ->postJson(route('projects.amocrm.ai-seed', $project), [
                'contacts' => [],
                'leads' => [],
                'links' => [],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['contacts']);
    }

    public function test_ai_seed_requires_connection(): void
    {
        config([
            'f5ai.api_key' => 'test-key',
            'f5ai.api_url' => 'https://api.f5ai.test/v2',
        ]);

        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'company_name' => 'ООО NoAmo',
        ]);

        $this->actingAs($user)
            ->postJson(route('projects.amocrm.ai-seed.preview', $project), [
                'prompt' => 'Создай 5 контактов',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Сначала подключите аккаунт amoCRM к проекту.');
    }

    /**
     * @return array{0: User, 1: Project}
     */
    private function makeConnectedProject(string $company): array
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'company_name' => $company,
        ]);
        $project->amocrmAccount()->create([
            'subdomain' => 'demo',
            'access_token' => 'token-123',
            'account_id' => 77,
            'account_name' => 'Demo',
        ]);

        return [$user, $project];
    }

    private function matchAmocrmCatalog(string $url): ?\GuzzleHttp\Promise\PromiseInterface
    {
        if (str_contains($url, '/contacts/custom_fields')) {
            return Http::response([
                '_embedded' => [
                    'custom_fields' => [
                        ['id' => 101, 'name' => 'Дата рождения', 'type' => 'birthday', 'code' => null, 'enums' => []],
                        ['id' => 102, 'name' => 'Телефон', 'type' => 'multitext', 'code' => 'PHONE', 'enums' => []],
                        ['id' => 103, 'name' => 'Email', 'type' => 'multitext', 'code' => 'EMAIL', 'enums' => []],
                    ],
                ],
            ]);
        }

        if (str_contains($url, '/leads/custom_fields')) {
            return Http::response([
                '_embedded' => ['custom_fields' => []],
            ]);
        }

        if (str_contains($url, '/leads/pipelines')) {
            return Http::response([
                '_embedded' => [
                    'pipelines' => [
                        [
                            'id' => 10,
                            'name' => 'Продажа украшений',
                            '_embedded' => [
                                'statuses' => [
                                    ['id' => 101, 'name' => 'Новая заявка'],
                                    ['id' => 102, 'name' => 'В работе'],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
        }

        return null;
    }
}
