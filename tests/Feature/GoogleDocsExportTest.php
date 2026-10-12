<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectAiMessage;
use App\Models\User;
use App\Services\GoogleDocsExportService;
use App\Services\GoogleOAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleDocsExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'http://localhost/google/callback',
        ]);
    }

    private function connectGoogle(User $user): User
    {
        $user->forceFill([
            'google_id' => 'gid-1',
            'google_email' => 'user@gmail.com',
            'google_access_token' => json_encode(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            'google_refresh_token' => 'refresh-test',
            'google_token_expires_at' => now()->addHour(),
        ])->save();

        return $user->fresh();
    }

    public function test_export_project_requires_google_connect(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $response = $this->actingAs($user)->postJson(route('projects.export.google-docs', $project));

        $response->assertStatus(409)
            ->assertJsonPath('needs_google_connect', true)
            ->assertJsonPath('connect_url', route('google.connect'));
    }

    public function test_export_project_to_google_docs_success(): void
    {
        $user = $this->connectGoogle(User::factory()->create());
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $this->mock(GoogleDocsExportService::class, function ($mock) {
            $mock->shouldReceive('uploadDocxAsGoogleDoc')
                ->once()
                ->withArgs(fn ($user, $path, $title, $existingId) => $existingId === null)
                ->andReturn([
                    'id' => 'doc-123',
                    'name' => 'ООО Тест',
                    'url' => 'https://docs.google.com/document/d/doc-123/edit',
                    'updated' => false,
                ]);
        });

        $response = $this->actingAs($user)->postJson(route('projects.export.google-docs', $project));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('document.id', 'doc-123')
            ->assertJsonPath('document.url', 'https://docs.google.com/document/d/doc-123/edit')
            ->assertJsonPath('document.updated', false)
            ->assertJsonPath('message', 'Документ сохранён в Google Docs.');

        $this->assertDatabaseHas('project_google_documents', [
            'project_id' => $project->id,
            'google_file_id' => 'doc-123',
            'source' => 'project_plan',
        ]);
    }

    public function test_export_project_to_google_docs_updates_existing(): void
    {
        $user = $this->connectGoogle(User::factory()->create());
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        \App\Models\ProjectGoogleDocument::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'google_file_id' => 'doc-existing',
            'name' => 'Старый план',
            'url' => 'https://docs.google.com/document/d/doc-existing/edit',
            'source' => \App\Models\ProjectGoogleDocument::SOURCE_PROJECT_PLAN,
        ]);

        $this->mock(GoogleDocsExportService::class, function ($mock) {
            $mock->shouldReceive('uploadDocxAsGoogleDoc')
                ->once()
                ->withArgs(fn ($user, $path, $title, $existingId) => $existingId === 'doc-existing')
                ->andReturn([
                    'id' => 'doc-existing',
                    'name' => 'ООО Тест',
                    'url' => 'https://docs.google.com/document/d/doc-existing/edit',
                    'updated' => true,
                ]);
        });

        $response = $this->actingAs($user)->postJson(route('projects.export.google-docs', $project));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('document.id', 'doc-existing')
            ->assertJsonPath('document.updated', true)
            ->assertJsonPath('message', 'Документ обновлён в Google Docs.');

        $this->assertSame(1, \App\Models\ProjectGoogleDocument::query()
            ->where('project_id', $project->id)
            ->where('source', 'project_plan')
            ->count());
    }

    public function test_export_meeting_message_to_google_docs(): void
    {
        $user = $this->connectGoogle(User::factory()->create());
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $message = $project->aiMessages()->create([
            'thread' => ProjectAiMessage::THREAD_MEETING,
            'role' => ProjectAiMessage::ROLE_ASSISTANT,
            'status' => ProjectAiMessage::STATUS_COMPLETED,
            'content' => "Повестка встречи\n1. Знакомство",
        ]);

        $this->mock(GoogleDocsExportService::class, function ($mock) {
            $mock->shouldReceive('uploadDocxAsGoogleDoc')
                ->once()
                ->andReturn([
                    'id' => 'meet-1',
                    'name' => 'Встреча',
                    'url' => 'https://docs.google.com/document/d/meet-1/edit',
                    'updated' => false,
                ]);
        });

        $response = $this->actingAs($user)->postJson(
            route('projects.ai.chat.export-google-docs', [$project, $message])
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('document.id', 'meet-1');
    }

    public function test_google_disconnect_clears_tokens(): void
    {
        $user = $this->connectGoogle(User::factory()->create());

        $this->mock(GoogleOAuthService::class, function ($mock) use ($user) {
            $mock->shouldReceive('disconnectUser')
                ->once()
                ->withArgs(fn (User $passed) => $passed->id === $user->id)
                ->andReturnUsing(function (User $passed): void {
                    $passed->forceFill([
                        'google_id' => null,
                        'google_email' => null,
                        'google_access_token' => null,
                        'google_refresh_token' => null,
                        'google_token_expires_at' => null,
                    ])->save();
                });
        });

        $response = $this->actingAs($user)->post(route('google.disconnect'));

        $response->assertRedirect(route('profile.edit'));
        $user->refresh();
        $this->assertFalse($user->hasGoogleConnected());
    }

    public function test_profile_shows_google_section(): void
    {
        $user = $this->connectGoogle(User::factory()->create());

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Profile/Index')
                ->where('user.google_connected', true)
                ->where('user.google_email', 'user@gmail.com')
                ->where('google.configured', true));
    }
}
