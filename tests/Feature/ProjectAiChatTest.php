<?php

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\FunnelStage;
use App\Models\Project;
use App\Models\ProjectAiMessage;
use App\Models\ProjectBriefDocument;
use App\Models\ProjectSkkField;
use App\Models\User;
use App\Support\F5WidgetCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProjectAiChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_page_requires_auth(): void
    {
        $project = Project::create([
            'user_id' => User::factory()->create()->id,
            'company_name' => 'Test Company',
        ]);

        $this->get(route('projects.ai.chat', $project))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_sees_chat_page(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $this->actingAs($user)
            ->get(route('projects.ai.chat', $project))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Projects/Ai/Chat')
                ->where('project.company_name', 'ООО Тест')
            );
    }

    public function test_authenticated_user_can_load_chat_messages(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        ProjectAiMessage::create([
            'project_id' => $project->id,
            'role' => ProjectAiMessage::ROLE_USER,
            'content' => 'Привет',
        ]);

        $this->actingAs($user)
            ->getJson(route('projects.ai.chat.messages', $project))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'messages');
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

        $apply->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('assistant_message.can_rollback', true);

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
        $this->assertDatabaseHas('project_ai_apply_snapshots', [
            'project_id' => $project->id,
            'project_ai_message_id' => $assistantId,
        ]);

        $rollback = $this->actingAs($user)->postJson(
            route('projects.ai.chat.rollback', [$project, $assistantId])
        );

        $rollback->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('assistant_message.can_rollback', false);

        $this->assertDatabaseMissing('project_skk_fields', [
            'project_id' => $project->id,
            'name' => 'Мобильный',
        ]);

        $this->assertDatabaseHas('project_skk_fields', [
            'project_id' => $project->id,
            'name' => 'Телефон',
            'comment' => 'Старый комментарий',
        ]);

        $restoredFunnel = Funnel::query()->where('project_id', $project->id)->first();
        $this->assertNotNull($restoredFunnel);
        $this->assertDatabaseHas('funnel_stages', [
            'funnel_id' => $restoredFunnel->id,
            'name' => 'Новый лид',
            'action' => 'Позвонить',
        ]);

        $this->assertNull(ProjectAiMessage::find($assistantId)->applied_at);
    }

    public function test_chat_apply_creates_integration_from_catalog_widget_slug(): void
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

        $widget = F5WidgetCatalog::findBySlug('chatter');
        $this->assertNotNull($widget);

        Http::fake([
            'https://api.f5ai.ru/v2/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'message' => 'Добавил Chatter для мессенджеров.',
                                'changes' => [
                                    'integrations' => [
                                        'create' => [
                                            ['widget_slug' => 'chatter', 'comment' => 'Единое окно переписки'],
                                        ],
                                        'update' => [],
                                        'delete' => [],
                                    ],
                                ],
                            ], JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ],
            ]),
        ]);

        $send = $this->actingAs($user)->postJson(route('projects.ai.chat.send', $project), [
            'message' => 'Добавь интеграцию Chatter',
        ]);

        $send->assertOk()->assertJsonPath('success', true);

        $assistantId = $send->json('messages.1.id');
        $changes = $send->json('messages.1.changes');

        $this->actingAs($user)->postJson(
            route('projects.ai.chat.apply', [$project, $assistantId]),
            ['changes' => $changes]
        )->assertOk();

        $this->assertDatabaseHas('project_integrations', [
            'project_id' => $project->id,
            'widget_slug' => 'chatter',
            'name' => $widget['name'],
            'widget_url' => $widget['url'],
            'comment' => 'Единое окно переписки',
        ]);
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

    public function test_apply_returns_json_when_changes_already_applied(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $message = ProjectAiMessage::create([
            'project_id' => $project->id,
            'role' => ProjectAiMessage::ROLE_ASSISTANT,
            'content' => 'Готово',
            'changes' => [
                'skk_fields' => [
                    'create' => [['entity_type' => 'deals', 'name' => 'Бюджет']],
                    'update' => [],
                    'delete' => [],
                ],
            ],
            'applied_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson(route('projects.ai.chat.apply', [$project, $message]), [])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_meeting_thread_returns_text_without_changes(): void
    {
        config([
            'f5ai.api_key' => 'test-key',
            'f5ai.api_url' => 'https://api.f5ai.ru/v2',
        ]);

        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
            'business_sphere' => 'Производство',
        ]);

        Http::fake([
            'https://api.f5ai.ru/v2/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => "## Структура встречи\n\n1. Цель\n2. Повестка\n3. Вопросы",
                        ],
                    ],
                ],
            ]),
        ]);

        $send = $this->actingAs($user)->postJson(route('projects.ai.chat.send', $project), [
            'message' => 'Подготовь структуру первичной встречи',
            'thread' => 'meeting',
        ]);

        $send->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('thread', 'meeting');

        $assistantId = $send->json('messages.1.id');
        $assistant = ProjectAiMessage::find($assistantId);

        $this->assertSame(ProjectAiMessage::THREAD_MEETING, $assistant->thread);
        $this->assertStringContainsString('Структура встречи', $assistant->content);
        $this->assertFalse($assistant->hasPendingChanges());
    }

    public function test_messages_endpoint_filters_by_thread(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        ProjectAiMessage::create([
            'project_id' => $project->id,
            'thread' => ProjectAiMessage::THREAD_PROJECT,
            'role' => ProjectAiMessage::ROLE_USER,
            'content' => 'Проект',
        ]);

        ProjectAiMessage::create([
            'project_id' => $project->id,
            'thread' => ProjectAiMessage::THREAD_MEETING,
            'role' => ProjectAiMessage::ROLE_USER,
            'content' => 'Встреча',
        ]);

        $this->actingAs($user)
            ->getJson(route('projects.ai.chat.messages', $project).'?thread=meeting')
            ->assertOk()
            ->assertJsonPath('thread', 'meeting')
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.content', 'Встреча');
    }

    public function test_meeting_message_can_be_exported_to_docx(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $message = ProjectAiMessage::create([
            'project_id' => $project->id,
            'thread' => ProjectAiMessage::THREAD_MEETING,
            'role' => ProjectAiMessage::ROLE_ASSISTANT,
            'status' => ProjectAiMessage::STATUS_COMPLETED,
            'content' => "## Структура встречи\n\n1. Цель\n2. Повестка\n- Вопросы клиенту",
        ]);

        $response = $this->actingAs($user)
            ->get(route('projects.ai.chat.export-docx', [$project, $message]));

        $response->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            (string) $response->headers->get('content-type')
        );
        $this->assertStringContainsString('.docx', (string) $response->headers->get('content-disposition'));
    }

    public function test_project_thread_message_cannot_export_meeting_docx(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $message = ProjectAiMessage::create([
            'project_id' => $project->id,
            'thread' => ProjectAiMessage::THREAD_PROJECT,
            'role' => ProjectAiMessage::ROLE_ASSISTANT,
            'status' => ProjectAiMessage::STATUS_COMPLETED,
            'content' => 'Обычный ответ по проекту',
        ]);

        $this->actingAs($user)
            ->get(route('projects.ai.chat.export-docx', [$project, $message]))
            ->assertNotFound();
    }

    public function test_audit_recommendations_thread_returns_text_without_changes(): void
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

        Http::fake([
            'https://api.f5ai.ru/v2/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => "## Резюме\n\nНужно упростить воронку.\n\n## Рекомендации по улучшению\n\n1. Сократить этапы",
                        ],
                    ],
                ],
            ]),
        ]);

        $send = $this->actingAs($user)->postJson(route('projects.ai.chat.send', $project), [
            'message' => 'Текст записи встречи: клиент жалуется на длинную воронку',
            'thread' => 'audit_recommendations',
        ]);

        $send->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('thread', 'audit_recommendations')
            ->assertJsonPath('processing_mode', 'audit_recommendations');

        $assistant = ProjectAiMessage::find($send->json('messages.1.id'));

        $this->assertSame(ProjectAiMessage::THREAD_AUDIT_RECOMMENDATIONS, $assistant->thread);
        $this->assertStringContainsString('Резюме', $assistant->content);
        $this->assertFalse($assistant->hasPendingChanges());
    }

    public function test_audit_recommendations_message_can_be_exported_to_docx(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $message = ProjectAiMessage::create([
            'project_id' => $project->id,
            'thread' => ProjectAiMessage::THREAD_AUDIT_RECOMMENDATIONS,
            'role' => ProjectAiMessage::ROLE_ASSISTANT,
            'status' => ProjectAiMessage::STATUS_COMPLETED,
            'content' => "## Резюме\n\nУпростите воронку.\n\n## Следующие шаги\n\n1. Согласовать этапы",
        ]);

        $response = $this->actingAs($user)
            ->get(route('projects.ai.chat.export-docx', [$project, $message]));

        $response->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            (string) $response->headers->get('content-type')
        );
        $disposition = (string) $response->headers->get('content-disposition');
        $this->assertTrue(
            str_contains($disposition, 'рекомендации_по_аудиту')
            || str_contains($disposition, 'rekomendacii_po_auditu')
            || str_contains($disposition, '%D1%80%D0%B5%D0%BA%D0%BE%D0%BC%D0%B5%D0%BD%D0%B4%D0%B0%D1%86%D0%B8%D0%B8'),
            "Unexpected Content-Disposition: {$disposition}"
        );
    }

    public function test_chat_page_includes_audit_recommendations_tab_payload(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $this->actingAs($user)
            ->get(route('projects.ai.chat', $project))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Projects/Ai/Chat')
                ->has('auditRecommendationMessages')
                ->has('aiPrompts.auditRecommendationActions', 3)
            );
    }

    public function test_processing_message_can_be_cancelled(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $userMessage = ProjectAiMessage::create([
            'project_id' => $project->id,
            'thread' => ProjectAiMessage::THREAD_MEETING,
            'role' => ProjectAiMessage::ROLE_USER,
            'content' => 'Подготовь структуру встречи',
            'status' => ProjectAiMessage::STATUS_COMPLETED,
        ]);

        $assistantMessage = ProjectAiMessage::create([
            'project_id' => $project->id,
            'thread' => ProjectAiMessage::THREAD_MEETING,
            'role' => ProjectAiMessage::ROLE_ASSISTANT,
            'content' => 'ИИ обрабатывает запрос…',
            'status' => ProjectAiMessage::STATUS_PROCESSING,
        ]);

        $this->actingAs($user)
            ->postJson(route('projects.ai.chat.cancel', [$project, $assistantMessage]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('assistant_message.status', ProjectAiMessage::STATUS_CANCELLED);

        $assistantMessage->refresh();

        $this->assertSame(ProjectAiMessage::STATUS_CANCELLED, $assistantMessage->status);
        $this->assertSame('Запрос отменён пользователем.', $assistantMessage->error_message);
    }

    public function test_completed_message_cannot_be_cancelled(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $message = ProjectAiMessage::create([
            'project_id' => $project->id,
            'role' => ProjectAiMessage::ROLE_ASSISTANT,
            'status' => ProjectAiMessage::STATUS_COMPLETED,
            'content' => 'Готово',
        ]);

        $this->actingAs($user)
            ->postJson(route('projects.ai.chat.cancel', [$project, $message]))
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_send_message_with_attached_document_includes_text_for_ai(): void
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

        $document = ProjectBriefDocument::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'kind' => ProjectBriefDocument::KIND_CHAT,
            'original_name' => 'notes.txt',
            'disk_path' => "project-briefs/{$project->id}/notes.txt",
            'mime_type' => 'text/plain',
            'size' => 40,
            'extracted_text' => 'Нужна воронка из трёх этапов для B2B.',
            'char_count' => 40,
        ]);

        Http::fake([
            'https://api.f5ai.ru/v2/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'message' => 'Учёл документ.',
                                'changes' => null,
                            ], JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ],
            ]),
        ]);

        $send = $this->actingAs($user)->postJson(route('projects.ai.chat.send', $project), [
            'message' => 'Что предложишь по документу?',
            'brief_document_id' => $document->id,
        ]);

        $send->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('messages.0.brief_document.original_name', 'notes.txt');

        $this->assertDatabaseHas('project_ai_messages', [
            'project_id' => $project->id,
            'role' => ProjectAiMessage::ROLE_USER,
            'brief_document_id' => $document->id,
        ]);

        Http::assertSent(function ($request) {
            $payload = $request->data();
            $messages = $payload['messages'] ?? [];
            $userContents = collect($messages)
                ->where('role', 'user')
                ->pluck('content')
                ->implode("\n");

            return str_contains($userContents, 'Прикреплённый документ «notes.txt»')
                && str_contains($userContents, 'Нужна воронка из трёх этапов для B2B.');
        });
    }

    public function test_send_message_with_multiple_attached_documents_includes_all_texts_for_ai(): void
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

        $first = ProjectBriefDocument::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'kind' => ProjectBriefDocument::KIND_CHAT,
            'original_name' => 'notes.txt',
            'disk_path' => "project-briefs/{$project->id}/notes.txt",
            'mime_type' => 'text/plain',
            'size' => 40,
            'extracted_text' => 'Нужна воронка из трёх этапов для B2B.',
            'char_count' => 40,
        ]);

        $second = ProjectBriefDocument::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'kind' => ProjectBriefDocument::KIND_CHAT,
            'original_name' => 'prices.txt',
            'disk_path' => "project-briefs/{$project->id}/prices.txt",
            'mime_type' => 'text/plain',
            'size' => 32,
            'extracted_text' => 'Прайс: настройка 50000 руб.',
            'char_count' => 32,
        ]);

        Http::fake([
            'https://api.f5ai.ru/v2/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'message' => 'Учёл оба документа.',
                                'changes' => null,
                            ], JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ],
            ]),
        ]);

        $send = $this->actingAs($user)->postJson(route('projects.ai.chat.send', $project), [
            'message' => 'Сравни документы.',
            'brief_document_ids' => [$first->id, $second->id],
        ]);

        $send->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('messages.0.brief_documents.0.original_name', 'notes.txt')
            ->assertJsonPath('messages.0.brief_documents.1.original_name', 'prices.txt');

        $userMessage = ProjectAiMessage::query()
            ->where('project_id', $project->id)
            ->where('role', ProjectAiMessage::ROLE_USER)
            ->latest('id')
            ->first();

        $this->assertSame([$first->id, $second->id], $userMessage->brief_document_ids);
        $this->assertSame($first->id, $userMessage->brief_document_id);

        Http::assertSent(function ($request) {
            $payload = $request->data();
            $messages = $payload['messages'] ?? [];
            $userContents = collect($messages)
                ->where('role', 'user')
                ->pluck('content')
                ->implode("\n");

            return str_contains($userContents, 'Прикреплённый документ «notes.txt»')
                && str_contains($userContents, 'Прикреплённый документ «prices.txt»')
                && str_contains($userContents, 'Прайс: настройка 50000 руб.');
        });
    }

    public function test_send_message_rejects_more_than_five_attachments(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $ids = [];
        for ($i = 1; $i <= 6; $i++) {
            $ids[] = ProjectBriefDocument::create([
                'project_id' => $project->id,
                'user_id' => $user->id,
                'kind' => ProjectBriefDocument::KIND_CHAT,
                'original_name' => "doc{$i}.txt",
                'disk_path' => "project-briefs/{$project->id}/doc{$i}.txt",
                'mime_type' => 'text/plain',
                'size' => 10,
                'extracted_text' => "Текст {$i}",
                'char_count' => 10,
            ])->id;
        }

        $this->actingAs($user)->postJson(route('projects.ai.chat.send', $project), [
            'message' => 'Проверка лимита.',
            'brief_document_ids' => $ids,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['brief_document_ids']);
    }

    public function test_kp_generation_requires_google_connection(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $this->actingAs($user)->postJson(route('projects.ai.chat.send', $project), [
            'message' => 'Собери КП по итогам встречи с клиентом.',
            'thread' => 'kp_generation',
        ])->assertStatus(409)
            ->assertJsonPath('needs_google_connect', true);
    }

    public function test_kp_generation_builds_presentation_and_exposes_document(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $user = User::factory()->create();
        $user->forceFill(['google_refresh_token' => 'refresh-test'])->save();

        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $pptx = tempnam(sys_get_temp_dir(), 'kp_test_');
        file_put_contents($pptx, 'pptx-binary-content');

        $this->mock(\App\Services\AiKpPresentationService::class, function ($mock) use ($pptx) {
            $mock->shouldReceive('build')
                ->once()
                ->withArgs(fn ($p, $u, $userId, $brief) => str_contains((string) $brief, 'воронка из 5 этапов'))
                ->andReturn([
                    'file_id' => 'file-123',
                    'name' => 'КП ООО Тест · 29.09.2026',
                    'url' => 'https://docs.google.com/presentation/d/file-123/edit',
                    'pptx_path' => $pptx,
                ]);
        });

        $send = $this->actingAs($user)->postJson(route('projects.ai.chat.send', $project), [
            'message' => 'Резюме встречи: клиент продаёт окна, нужна воронка из 5 этапов.',
            'thread' => 'kp_generation',
        ]);

        $send->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('processing_mode', 'kp_generation')
            ->assertJsonPath('messages.1.kp_document.name', 'КП ООО Тест · 29.09.2026')
            ->assertJsonPath('messages.1.kp_document.url', 'https://docs.google.com/presentation/d/file-123/edit');

        $assistant = ProjectAiMessage::query()
            ->where('project_id', $project->id)
            ->where('role', ProjectAiMessage::ROLE_ASSISTANT)
            ->latest('id')
            ->first();

        $this->assertSame(ProjectAiMessage::STATUS_COMPLETED, $assistant->status);
        $this->assertSame(ProjectAiMessage::THREAD_KP_GENERATION, $assistant->thread);

        $this->assertDatabaseHas('project_google_documents', [
            'project_id' => $project->id,
            'google_file_id' => 'file-123',
            'source' => \App\Models\ProjectGoogleDocument::SOURCE_KP_PRESENTATION,
            'project_ai_message_id' => $assistant->id,
        ]);

        $this->assertNotNull($send->json('messages.1.kp_document.download_url'));

        $this->actingAs($user)
            ->get(route('projects.ai.chat.kp-pptx', [$project, $assistant]))
            ->assertOk();
    }

    public function test_chat_page_exposes_brief_documents_store_url(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $this->actingAs($user)
            ->get(route('projects.ai.chat', $project))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Projects/Ai/Chat')
                ->where('project.urls.brief_documents_store', route('projects.brief-documents.store', $project))
            );
    }
}
