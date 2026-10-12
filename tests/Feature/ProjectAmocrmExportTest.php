<?php

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\FunnelStage;
use App\Models\Project;
use App\Models\ProjectSkkField;
use App\Models\User;
use App\Support\AmoCrmMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProjectAmocrmExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalize_subdomain(): void
    {
        $this->assertSame('client', AmoCrmMapper::normalizeSubdomain('https://client.amocrm.ru/'));
        $this->assertSame('client', AmoCrmMapper::normalizeSubdomain('CLIENT.amocrm.ru'));
        $this->assertSame('client', AmoCrmMapper::normalizeSubdomain('client'));
    }

    public function test_connect_requires_valid_token(): void
    {
        Http::fake([
            'https://demo.amocrm.ru/api/v4/account*' => Http::response(['title' => 'Unauthorized'], 401),
        ]);

        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $response = $this->actingAs($user)->post(route('projects.amocrm.connect', $project), [
            'subdomain' => 'demo',
            'access_token' => 'bad-token',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('project_amocrm_accounts', ['project_id' => $project->id]);
    }

    public function test_connect_and_export_pipelines_and_fields(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $funnel = Funnel::create([
            'project_id' => $project->id,
            'name' => 'Продажи',
            'sort_order' => 1,
        ]);

        $stageNew = FunnelStage::create([
            'funnel_id' => $funnel->id,
            'name' => 'Новая',
            'sort_order' => 1,
            'color' => '#99ccff',
            'action' => 'Позвонить клиенту',
            'comment' => 'Уточнить бюджет и сроки',
        ]);

        $stageWon = FunnelStage::create([
            'funnel_id' => $funnel->id,
            'name' => 'Успешно реализовано',
            'sort_order' => 2,
            'color' => '#99ff99',
        ]);

        $field = ProjectSkkField::create([
            'project_id' => $project->id,
            'entity_type' => ProjectSkkField::TYPE_DEALS,
            'name' => 'Бюджет',
            'value_type' => ProjectSkkField::VALUE_NUMBER,
            'is_required' => false,
            'show_in_card' => true,
            'show_in_list' => false,
            'show_in_filter' => false,
            'sort_order' => 1,
        ]);

        Http::fake([
            'https://demo.amocrm.ru/api/v4/account*' => Http::response([
                'id' => 111,
                'name' => 'Demo Account',
                'subdomain' => 'demo',
            ]),
            'https://demo.amocrm.ru/api/v4/leads/pipelines/*/statuses/*' => Http::response([
                'id' => 10,
                'name' => 'Новая',
            ]),
            'https://demo.amocrm.ru/api/v4/leads/pipelines/*' => Http::response([
                'id' => 5001,
                'name' => 'Продажи',
                '_embedded' => [
                    'statuses' => [
                        ['id' => 10, 'name' => 'Новая', 'type' => 0],
                        ['id' => 142, 'name' => 'Успешно реализовано', 'type' => 0],
                    ],
                ],
            ]),
            'https://demo.amocrm.ru/api/v4/leads/pipelines' => Http::response([
                '_embedded' => [
                    'pipelines' => [[
                        'id' => 5001,
                        'name' => 'Продажи',
                        // Без request_id — как часто отвечает реальный amo
                        '_embedded' => [
                            'statuses' => [
                                ['id' => 10, 'name' => 'Новая', 'type' => 0],
                                ['id' => 142, 'name' => 'Успешно реализовано', 'type' => 0],
                            ],
                        ],
                    ]],
                ],
            ]),
            'https://demo.amocrm.ru/api/v4/leads/custom_fields' => Http::response([
                '_embedded' => [
                    'custom_fields' => [[
                        'id' => 9001,
                        'name' => 'Бюджет',
                        'request_id' => (string) $field->id,
                    ]],
                ],
            ]),
        ]);

        $connect = $this->actingAs($user)->post(route('projects.amocrm.connect', $project), [
            'subdomain' => 'https://demo.amocrm.ru',
            'access_token' => 'long-lived-token',
        ]);

        $connect->assertRedirect();
        $connect->assertSessionHas('success');

        $export = $this->actingAs($user)->post(route('projects.amocrm.export', $project));
        $export->assertRedirect();
        $export->assertSessionHas('success');

        $this->assertSame(5001, $funnel->fresh()->amocrm_id);
        $this->assertSame(10, $stageNew->fresh()->amocrm_id);
        $this->assertSame(142, $stageWon->fresh()->amocrm_id);
        $this->assertSame(9001, $field->fresh()->amocrm_id);

        Http::assertNotSent(function ($request) {
            return $request->method() === 'PATCH'
                && (
                    str_contains($request->url(), '/statuses/142')
                    || str_contains($request->url(), '/statuses/143')
                );
        });

        Http::assertSent(function ($request) {
            if ($request->method() !== 'PATCH' || ! str_contains($request->url(), '/statuses/10')) {
                return false;
            }

            $descriptions = $request['descriptions'] ?? null;

            return is_array($descriptions)
                && count($descriptions) === 3
                && str_contains($descriptions[0]['description'], 'Позвонить клиенту')
                && str_contains($descriptions[0]['description'], 'Уточнить бюджет');
        });

        $account = $project->fresh()->amocrmAccount;
        $this->assertSame(1, $account->last_export_result['tips_synced'] ?? 0);

        $second = $this->actingAs($user)->post(route('projects.amocrm.export', $project));
        $second->assertRedirect();
        $second->assertSessionHas('success');
    }

    public function test_export_recreates_missing_pipeline(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $project->amocrmAccount()->create([
            'subdomain' => 'demo',
            'access_token' => 'token',
            'account_id' => 111,
            'account_name' => 'Demo',
        ]);

        $funnel = Funnel::create([
            'project_id' => $project->id,
            'name' => 'Продажи',
            'sort_order' => 1,
            'amocrm_id' => 11105902,
        ]);

        $stage = FunnelStage::create([
            'funnel_id' => $funnel->id,
            'name' => 'Новая сделка',
            'sort_order' => 1,
            'color' => '#99ccff',
            'comment' => 'Подсказка',
            'amocrm_id' => 55,
        ]);

        Http::fake([
            'https://demo.amocrm.ru/api/v4/leads/pipelines/11105902' => Http::response([
                'detail' => 'Pipeline ID 11105902 is not found',
            ], 404),
            'https://demo.amocrm.ru/api/v4/leads/pipelines/*/statuses/*' => Http::response([
                'id' => 77,
                'name' => 'Новая сделка',
            ]),
            'https://demo.amocrm.ru/api/v4/leads/pipelines/*' => Http::response([
                'id' => 9000,
                'name' => 'Продажи',
                '_embedded' => [
                    'statuses' => [
                        ['id' => 77, 'name' => 'Новая сделка', 'type' => 0],
                    ],
                ],
            ]),
            'https://demo.amocrm.ru/api/v4/leads/pipelines' => Http::response([
                '_embedded' => [
                    'pipelines' => [[
                        'id' => 9000,
                        'name' => 'Продажи',
                        '_embedded' => [
                            'statuses' => [[
                                'id' => 77,
                                'name' => 'Новая сделка',
                                'type' => 0,
                            ]],
                        ],
                    ]],
                ],
            ]),
        ]);

        $response = $this->actingAs($user)->post(route('projects.amocrm.export', $project));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertSame(9000, $funnel->fresh()->amocrm_id);
        $this->assertSame(77, $stage->fresh()->amocrm_id);
        $this->assertSame(1, $project->fresh()->amocrmAccount->last_export_result['tips_synced'] ?? 0);
    }

    public function test_preview_compares_project_with_existing_amo_structure(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $project->amocrmAccount()->create([
            'subdomain' => 'demo',
            'access_token' => 'token',
            'account_id' => 111,
            'account_name' => 'Demo',
        ]);

        $funnel = Funnel::create([
            'project_id' => $project->id,
            'name' => 'Продажи',
            'sort_order' => 1,
        ]);

        FunnelStage::create([
            'funnel_id' => $funnel->id,
            'name' => 'Новая',
            'sort_order' => 1,
        ]);

        FunnelStage::create([
            'funnel_id' => $funnel->id,
            'name' => 'Согласование',
            'sort_order' => 2,
        ]);

        ProjectSkkField::create([
            'project_id' => $project->id,
            'entity_type' => ProjectSkkField::TYPE_DEALS,
            'name' => 'Бюджет',
            'value_type' => ProjectSkkField::VALUE_NUMBER,
            'is_required' => false,
            'show_in_card' => true,
            'show_in_list' => false,
            'show_in_filter' => false,
            'sort_order' => 1,
        ]);

        ProjectSkkField::create([
            'project_id' => $project->id,
            'entity_type' => ProjectSkkField::TYPE_CONTACTS,
            'name' => 'Telegram',
            'value_type' => ProjectSkkField::VALUE_TEXT,
            'is_required' => false,
            'show_in_card' => true,
            'show_in_list' => false,
            'show_in_filter' => false,
            'sort_order' => 1,
        ]);

        Http::fake([
            'https://demo.amocrm.ru/api/v4/leads/pipelines*' => Http::response([
                '_embedded' => [
                    'pipelines' => [[
                        'id' => 5001,
                        'name' => 'Продажи',
                        '_embedded' => [
                            'statuses' => [
                                ['id' => 10, 'name' => 'Новая', 'type' => 0],
                            ],
                        ],
                    ]],
                ],
            ]),
            'https://demo.amocrm.ru/api/v4/leads/custom_fields*' => Http::response([
                '_embedded' => [
                    'custom_fields' => [[
                        'id' => 9001,
                        'name' => 'Бюджет',
                        'type' => 'numeric',
                    ]],
                ],
            ]),
            'https://demo.amocrm.ru/api/v4/contacts/custom_fields*' => Http::response([
                '_embedded' => ['custom_fields' => []],
            ]),
            'https://demo.amocrm.ru/api/v4/companies/custom_fields*' => Http::response([
                '_embedded' => ['custom_fields' => []],
            ]),
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('projects.amocrm.preview', $project));

        if ($response->status() !== 200) {
            $this->fail('Preview failed: '.$response->getContent());
        }

        $response->assertOk();
        $response->assertJsonPath('summary.pipelines_conflicts', 1);
        $response->assertJsonPath('summary.stages_conflicts', 1);
        $response->assertJsonPath('summary.stages_create', 1);
        $response->assertJsonPath('summary.fields_existing', 1);
        $response->assertJsonPath('summary.fields_create', 1);
        $response->assertJsonPath('funnels.0.status', 'conflict');
        $response->assertJsonPath('fields.0.status', 'create');
        $response->assertJsonPath('fields.1.status', 'existing');
    }

    public function test_ai_audit_analyzes_amo_structure_and_saves_result(): void
    {
        config([
            'f5ai.api_key' => 'test-key',
            'f5ai.api_url' => 'https://api.f5ai.ru/v2',
            'f5ai.model' => 'gpt-5.4',
        ]);

        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
            'business_sphere' => 'Образование',
            'client_tasks' => 'Автоматизировать заявки и повторные касания',
        ]);

        $project->amocrmAccount()->create([
            'subdomain' => 'demo',
            'access_token' => 'token',
            'account_id' => 111,
            'account_name' => 'Demo',
        ]);

        Http::fake([
            'https://demo.amocrm.ru/api/v4/account*' => Http::response([
                'id' => 111,
                'name' => 'Demo',
                'subdomain' => 'demo',
            ]),
            'https://demo.amocrm.ru/api/v4/leads/pipelines*' => Http::response([
                '_embedded' => [
                    'pipelines' => [[
                        'id' => 5001,
                        'name' => 'Продажи',
                        '_embedded' => [
                            'statuses' => [
                                ['id' => 10, 'name' => 'Новая заявка', 'type' => 0, 'sort' => 10],
                                ['id' => 20, 'name' => 'Повторный контакт', 'type' => 0, 'sort' => 20],
                            ],
                        ],
                    ]],
                ],
            ]),
            'https://demo.amocrm.ru/api/v4/leads/custom_fields*' => Http::response([
                '_embedded' => [
                    'custom_fields' => [[
                        'id' => 9001,
                        'name' => 'Источник заявки',
                        'type' => 'select',
                    ]],
                ],
            ]),
            'https://demo.amocrm.ru/api/v4/contacts/custom_fields*' => Http::response([
                '_embedded' => ['custom_fields' => []],
            ]),
            'https://demo.amocrm.ru/api/v4/companies/custom_fields*' => Http::response([
                '_embedded' => ['custom_fields' => []],
            ]),
            'https://demo.amocrm.ru/api/v4/*' => Http::response(['_embedded' => []], 200),
            'https://demo.amocrm.ru/ajax/*' => Http::response([], 404),
            'https://api.f5ai.ru/v2/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => "## Быстрые победы\n- Добавить автоматическую задачу на повторный контакт.\n\n[КОНЕЦ АУДИТА]",
                    ],
                ]],
            ]),
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('projects.amocrm.audit', $project), [
                'pipeline_ids' => [5001],
                'include_fields' => true,
            ]);

        $response->assertOk();
        $response->assertJsonPath('content', "## Быстрые победы\n- Добавить автоматическую задачу на повторный контакт.");

        $account = $project->fresh()->amocrmAccount;
        $this->assertNotNull($account->last_ai_audit_at);
        $this->assertStringContainsString('Быстрые победы', $account->last_ai_audit_result);

        $this->assertDatabaseHas('project_ai_messages', [
            'project_id' => $project->id,
            'user_id' => $user->id,
            'thread' => 'project',
            'role' => 'user',
            'content' => 'AI-аудит amoCRM (Продажи; с полями)',
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('project_ai_messages', [
            'project_id' => $project->id,
            'user_id' => $user->id,
            'thread' => 'project',
            'role' => 'assistant',
            'content' => "## Быстрые победы\n- Добавить автоматическую задачу на повторный контакт.",
            'status' => 'completed',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.f5ai.ru/v2/chat/completions'
                && str_contains($request['messages'][1]['content'] ?? '', 'Повторный контакт');
        });

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.f5ai.ru/v2/chat/completions'
                && str_contains($request['messages'][1]['content'] ?? '', 'f5_widgets_catalog');
        });

        Http::assertSent(function ($request) {
            $system = $request['messages'][0]['content'] ?? '';

            return $request->url() === 'https://api.f5ai.ru/v2/chat/completions'
                && str_contains($system, '## Советы по улучшению продаж')
                && str_contains($system, 'кастомные виджеты');
        });
    }

    public function test_ai_audit_continues_when_first_response_is_truncated(): void
    {
        config([
            'f5ai.api_key' => 'test-key',
            'f5ai.api_url' => 'https://api.f5ai.ru/v2',
            'f5ai.model' => 'gpt-5.4',
        ]);

        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $project->amocrmAccount()->create([
            'subdomain' => 'demo',
            'access_token' => 'token',
            'account_id' => 111,
            'account_name' => 'Demo',
        ]);

        Http::fake([
            'https://demo.amocrm.ru/api/v4/leads/pipelines' => Http::response([
                '_embedded' => [
                    'pipelines' => [[
                        'id' => 5001,
                        'name' => 'Продажи',
                        '_embedded' => [
                            'statuses' => [
                                ['id' => 10, 'name' => 'Новая', 'type' => 0, 'sort' => 10],
                            ],
                        ],
                    ]],
                ],
            ]),
            'https://demo.amocrm.ru/api/v4/leads/custom_fields' => Http::response([
                '_embedded' => ['custom_fields' => []],
            ]),
            'https://demo.amocrm.ru/api/v4/contacts/custom_fields' => Http::response([
                '_embedded' => ['custom_fields' => []],
            ]),
            'https://demo.amocrm.ru/api/v4/companies/custom_fields' => Http::response([
                '_embedded' => ['custom_fields' => []],
            ]),
            'https://api.f5ai.ru/v2/chat/completions' => Http::sequence()
                ->push([
                    'choices' => [[
                        'message' => ['content' => "## Аудит\nПервая часть"],
                    ]],
                ])
                ->push([
                    'choices' => [[
                        'message' => ['content' => "Вторая часть\n\n[КОНЕЦ АУДИТА]"],
                    ]],
                ]),
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('projects.amocrm.audit', $project), [
                'pipeline_ids' => [5001],
                'include_fields' => false,
            ]);

        $response->assertOk();
        $response->assertJsonPath('content', "## Аудит\nПервая часть\n\nВторая часть");

        $account = $project->fresh()->amocrmAccount;
        $this->assertSame("## Аудит\nПервая часть\n\nВторая часть", $account->last_ai_audit_result);

        $this->assertDatabaseHas('project_ai_messages', [
            'project_id' => $project->id,
            'role' => 'user',
            'content' => 'AI-аудит amoCRM (Продажи; без полей)',
        ]);
        $this->assertDatabaseHas('project_ai_messages', [
            'project_id' => $project->id,
            'role' => 'assistant',
            'content' => "## Аудит\nПервая часть\n\nВторая часть",
        ]);
    }

    public function test_ai_audit_can_be_downloaded_as_docx(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $project->amocrmAccount()->create([
            'subdomain' => 'demo',
            'access_token' => 'token',
            'account_id' => 111,
            'account_name' => 'Demo',
            'last_ai_audit_at' => now(),
            'last_ai_audit_result' => "## Рекомендации\n- Подключить виджет F5 для автоматизации заявок.",
        ]);

        $response = $this->actingAs($user)
            ->get(route('projects.amocrm.audit-docx', $project));

        $response->assertOk();
        $response->assertHeader('content-disposition');
    }

    public function test_ai_audit_docx_requires_existing_audit_result(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $project->amocrmAccount()->create([
            'subdomain' => 'demo',
            'access_token' => 'token',
            'account_id' => 111,
            'account_name' => 'Demo',
        ]);

        $response = $this->actingAs($user)
            ->get(route('projects.amocrm.audit-docx', $project));

        $response->assertStatus(422);
    }

    public function test_stage_tip_text_combines_action_and_comment(): void
    {
        $this->assertSame(
            "Автозадача: Позвонить\n\nУточнить бюджет",
            AmoCrmMapper::stageTipText('Позвонить', 'Уточнить бюджет')
        );
        $this->assertStringContainsString(
            'Тип: Квалификация',
            AmoCrmMapper::stageTipText('Позвонить', null, 'Квалификация', '2 дня')
        );
        $this->assertStringContainsString(
            'Автозадача: Позвонить',
            AmoCrmMapper::stageTipText('Позвонить', null, 'Квалификация', '2 дня')
        );

        $this->assertNull(AmoCrmMapper::stageTipText('  ', null));
        $this->assertCount(3, AmoCrmMapper::stageDescriptions('x', null));
    }

    public function test_success_and_lost_stage_name_detection(): void
    {
        $this->assertTrue(AmoCrmMapper::isSuccessStageName('Успешно реализовано'));
        $this->assertTrue(AmoCrmMapper::isSuccessStageName('Сделка успешно завершена'));
        $this->assertFalse(AmoCrmMapper::isSuccessStageName('Успешный звонок'));

        $this->assertTrue(AmoCrmMapper::isLostStageName('Закрыто и не реализовано'));
        $this->assertTrue(AmoCrmMapper::isLostStageName('Клиент отказался от повторных касаний'));
        $this->assertFalse(AmoCrmMapper::isLostStageName('Закрытие документов'));
    }

    public function test_export_requires_connection(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $response = $this->actingAs($user)->post(route('projects.amocrm.export', $project));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_export_respects_selected_funnels_and_fields(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Выбор',
        ]);

        $project->amocrmAccount()->create([
            'subdomain' => 'demo',
            'access_token' => 'token',
            'account_id' => 111,
            'account_name' => 'Demo',
        ]);

        $funnelA = Funnel::create([
            'project_id' => $project->id,
            'name' => 'Продажи',
            'sort_order' => 1,
        ]);
        FunnelStage::create([
            'funnel_id' => $funnelA->id,
            'name' => 'Новая',
            'sort_order' => 1,
            'color' => '#99ccff',
        ]);

        $funnelB = Funnel::create([
            'project_id' => $project->id,
            'name' => 'Сервис',
            'sort_order' => 2,
        ]);
        FunnelStage::create([
            'funnel_id' => $funnelB->id,
            'name' => 'Обращение',
            'sort_order' => 1,
            'color' => '#ffcc66',
        ]);

        $fieldKeep = ProjectSkkField::create([
            'project_id' => $project->id,
            'entity_type' => ProjectSkkField::TYPE_DEALS,
            'name' => 'Бюджет',
            'value_type' => ProjectSkkField::VALUE_NUMBER,
            'is_required' => false,
            'show_in_card' => true,
            'show_in_list' => false,
            'show_in_filter' => false,
            'sort_order' => 1,
        ]);
        $fieldSkip = ProjectSkkField::create([
            'project_id' => $project->id,
            'entity_type' => ProjectSkkField::TYPE_CONTACTS,
            'name' => 'Город',
            'value_type' => ProjectSkkField::VALUE_TEXT,
            'is_required' => false,
            'show_in_card' => true,
            'show_in_list' => false,
            'show_in_filter' => false,
            'sort_order' => 2,
        ]);

        Http::fake([
            'https://demo.amocrm.ru/api/v4/leads/pipelines/*/statuses/*' => Http::response([
                'id' => 10,
                'name' => 'Новая',
            ]),
            'https://demo.amocrm.ru/api/v4/leads/pipelines/*' => Http::response([
                'id' => 5001,
                'name' => 'Продажи',
                '_embedded' => [
                    'statuses' => [
                        ['id' => 10, 'name' => 'Новая', 'type' => 0],
                    ],
                ],
            ]),
            'https://demo.amocrm.ru/api/v4/leads/pipelines' => Http::response([
                '_embedded' => [
                    'pipelines' => [[
                        'id' => 5001,
                        'name' => 'Продажи',
                        '_embedded' => [
                            'statuses' => [
                                ['id' => 10, 'name' => 'Новая', 'type' => 0],
                            ],
                        ],
                    ]],
                ],
            ]),
            'https://demo.amocrm.ru/api/v4/leads/custom_fields' => Http::response([
                '_embedded' => [
                    'custom_fields' => [[
                        'id' => 9001,
                        'name' => 'Бюджет',
                        'request_id' => (string) $fieldKeep->id,
                    ]],
                ],
            ]),
            'https://demo.amocrm.ru/api/v4/contacts/custom_fields' => Http::response([
                '_embedded' => [
                    'custom_fields' => [[
                        'id' => 9002,
                        'name' => 'Город',
                        'request_id' => (string) $fieldSkip->id,
                    ]],
                ],
            ]),
        ]);

        $response = $this->actingAs($user)->post(route('projects.amocrm.export', $project), [
            'funnel_ids' => [$funnelA->id],
            'include_fields' => true,
            'field_ids' => [$fieldKeep->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertSame(5001, $funnelA->fresh()->amocrm_id);
        $this->assertNull($funnelB->fresh()->amocrm_id);
        $this->assertSame(9001, $fieldKeep->fresh()->amocrm_id);
        $this->assertNull($fieldSkip->fresh()->amocrm_id);

        $empty = $this->actingAs($user)->post(route('projects.amocrm.export', $project), [
            'funnel_ids' => [],
            'include_fields' => false,
            'field_ids' => [],
        ]);
        $empty->assertSessionHasErrors('funnel_ids');
    }

    public function test_disconnect_removes_account(): void
    {
        Http::fake([
            'https://demo.amocrm.ru/api/v4/account*' => Http::response([
                'id' => 111,
                'name' => 'Demo Account',
                'subdomain' => 'demo',
            ]),
        ]);

        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $this->actingAs($user)->post(route('projects.amocrm.connect', $project), [
            'subdomain' => 'demo',
            'access_token' => 'token',
        ])->assertSessionHas('success');

        $this->actingAs($user)
            ->delete(route('projects.amocrm.disconnect', $project))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('project_amocrm_accounts', ['project_id' => $project->id]);
    }
}
