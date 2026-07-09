<?php

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\FunnelStage;
use App\Models\Project;
use App\Models\ProjectAiMessage;
use App\Models\ProjectSkkField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProjectAiChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') === 'mysql') {
            $this->markTestSkipped('RefreshDatabase must not run against the development MySQL database.');
        }

        parent::setUp();
    }

    public function test_chat_page_requires_auth(): void
    {
        $project = Project::create([
            'user_id' => User::factory()->create()->id,
            'company_name' => 'Test Company',
        ]);

        $this->get(route('projects.ai.chat', $project))->assertRedirect(route('login'));
    }

    public function test_send_message_and_apply_changes(): void
    {
        config([
            'f5ai.api_key' => 'test-key',
            'f5ai.api_url' => 'https://api.f5ai.ru/v2',
        ]);

        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $field = ProjectSkkField::create([
            'project_id' => $project->id,
            'entity_type' => 'contacts',
            'name' => 'Телефон',
            'comment' => 'Старый комментарий',
            'sort_order' => 1,
        ]);

        $funnel = Funnel::create([
            'project_id' => $project->id,
            'name' => 'Продажи',
            'sort_order' => 1,
        ]);

        $stage = FunnelStage::create([
            'funnel_id' => $funnel->id,
            'name' => 'Новый лид',
            'action' => 'Позвонить',
            'sort_order' => 1,
        ]);

        Http::fake([
            'https://api.f5ai.ru/v2/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'message' => 'Обновил поле и этап.',
                                'changes' => [
                                    'skk_fields' => [
                                        'update' => [
                                            ['id' => $field->id, 'name' => 'Мобильный', 'comment' => 'Основной номер'],
                                        ],
                                    ],
                                    'stages' => [
                                        'update' => [
                                            ['id' => $stage->id, 'name' => 'Входящий лид', 'action' => 'Связаться в течение часа'],
                                        ],
                                    ],
                                ],
                            ], JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ],
            ]),
        ]);

        $send = $this->actingAs($user)->postJson(route('projects.ai.chat.send', $project), [
            'message' => 'Переименуй поле телефона и первый этап воронки',
        ]);

        $send->assertOk()->assertJsonPath('success', true);

        $assistantId = $send->json('messages.1.id');
        $changes = $send->json('messages.1.changes');

        $apply = $this->actingAs($user)->postJson(
            route('projects.ai.chat.apply', [$project, $assistantId]),
            ['changes' => $changes]
        );

        $apply->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('project_skk_fields', [
            'id' => $field->id,
            'name' => 'Мобильный',
            'comment' => 'Основной номер',
        ]);

        $this->assertDatabaseHas('funnel_stages', [
            'id' => $stage->id,
            'name' => 'Входящий лид',
            'action' => 'Связаться в течение часа',
        ]);

        $this->assertNotNull(ProjectAiMessage::find($assistantId)->applied_at);
    }

    public function test_has_pending_changes_with_real_proposal_data(): void
    {
        $message = new ProjectAiMessage([
            'role' => ProjectAiMessage::ROLE_ASSISTANT,
            'changes' => [
                'project' => ['fields' => ['company_name' => 'Test']],
                'funnels' => ['create' => [['name' => 'Sales', 'stages' => []]], 'update' => [], 'delete' => []],
            ],
        ]);

        $this->assertTrue($message->hasPendingChanges());
    }
}
