<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectAiMessage;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UserNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_access_creates_notification_for_new_editor(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $owner->id,
            'company_name' => 'ООО Тест',
        ]);

        $this->actingAs($owner)
            ->put(route('projects.editors.update', $project), [
                'user_ids' => [$editor->id],
            ])
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $editor->id,
            'type' => UserNotification::TYPE_EDITOR_ACCESS,
        ]);

        $this->actingAs($editor)
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('notifications', 1)
                ->where('notifications.0.type', UserNotification::TYPE_EDITOR_ACCESS)
            );
    }

    public function test_ai_completion_creates_notification_for_requester(): void
    {
        config([
            'f5ai.api_key' => 'test-key',
            'f5ai.api_url' => 'https://api.f5ai.ru/v2',
        ]);

        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        Http::fake([
            'https://api.f5ai.ru/v2/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Краткий ответ без изменений.',
                        ],
                    ],
                ],
            ]),
        ]);

        $this->actingAs($user)
            ->postJson(route('projects.ai.chat.send', $project), [
                'message' => 'Привет',
                'thread' => ProjectAiMessage::THREAD_PROJECT,
            ])
            ->assertOk();

        $this->assertDatabaseHas('project_ai_messages', [
            'project_id' => $project->id,
            'role' => ProjectAiMessage::ROLE_ASSISTANT,
            'user_id' => $user->id,
        ]);

        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $user->id)
                ->whereIn('type', [
                    UserNotification::TYPE_AI_READY,
                    UserNotification::TYPE_AI_FAILED,
                ])
                ->exists()
        );
    }

    public function test_user_can_dismiss_own_notification(): void
    {
        $user = User::factory()->create();
        $notification = UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => UserNotification::TYPE_EDITOR_ACCESS,
            'title' => 'Тест',
            'body' => 'Тело',
            'url' => '/',
        ]);

        $this->actingAs($user)
            ->postJson(route('notifications.dismiss', $notification))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertNotNull($notification->fresh()->read_at);

        $this->actingAs($user)
            ->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonCount(0, 'notifications');
    }

    public function test_user_cannot_dismiss_foreign_notification(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $notification = UserNotification::query()->create([
            'user_id' => $owner->id,
            'type' => UserNotification::TYPE_EDITOR_ACCESS,
            'title' => 'Тест',
        ]);

        $this->actingAs($other)
            ->postJson(route('notifications.dismiss', $notification))
            ->assertForbidden();
    }
}
