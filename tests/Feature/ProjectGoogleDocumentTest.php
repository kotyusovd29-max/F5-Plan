<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectGoogleDocument;
use App\Models\User;
use App\Services\GoogleDocsExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectGoogleDocumentTest extends TestCase
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

    public function test_documents_page_lists_project_google_docs(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        ProjectGoogleDocument::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'google_file_id' => 'doc-1',
            'name' => 'План ООО Тест',
            'url' => 'https://docs.google.com/document/d/doc-1/edit',
            'source' => ProjectGoogleDocument::SOURCE_PROJECT_PLAN,
        ]);

        $this->actingAs($user)
            ->get(route('projects.documents.index', $project))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Projects/Documents')
                ->has('documents', 1)
                ->where('documents.0.name', 'План ООО Тест'));
    }

    public function test_export_stores_google_document_on_project(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'google_refresh_token' => 'refresh',
            'google_access_token' => json_encode(['access_token' => 'token']),
            'google_email' => 'user@gmail.com',
        ])->save();

        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $this->mock(GoogleDocsExportService::class, function ($mock) {
            $mock->shouldReceive('uploadDocxAsGoogleDoc')
                ->once()
                ->andReturn([
                    'id' => 'saved-1',
                    'name' => 'ООО Тест',
                    'url' => 'https://docs.google.com/document/d/saved-1/edit',
                    'updated' => false,
                ]);
        });

        $this->actingAs($user)
            ->postJson(route('projects.export.google-docs', $project))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('project_google_documents', [
            'project_id' => $project->id,
            'google_file_id' => 'saved-1',
            'source' => ProjectGoogleDocument::SOURCE_PROJECT_PLAN,
        ]);
    }

    public function test_can_remove_document_from_project_list(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $document = ProjectGoogleDocument::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'google_file_id' => 'doc-2',
            'name' => 'План',
            'url' => 'https://docs.google.com/document/d/doc-2/edit',
            'source' => ProjectGoogleDocument::SOURCE_PROJECT_PLAN,
        ]);

        $this->actingAs($user)
            ->delete(route('projects.documents.destroy', [$project, $document]))
            ->assertRedirect(route('projects.documents.index', $project));

        $this->assertDatabaseMissing('project_google_documents', ['id' => $document->id]);
    }
}
