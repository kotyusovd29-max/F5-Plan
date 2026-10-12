<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectAiMessage;
use App\Models\User;
use App\Services\ProjectAiReportExtractor;
use App\Services\ProjectDocxExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProjectAiEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_show_includes_ai_prompts_and_urls(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Projects/Show')
                ->has('aiPrompts.analysis')
                ->has('aiPrompts.actions', 2)
                ->where('project.urls.ai_analyze', route('projects.ai.analyze', $project))
                ->where('project.urls.ai_generate', route('projects.ai.generate', $project))
            );
    }

    public function test_project_analysis_endpoint_returns_recommendations(): void
    {
        config([
            'f5ai.api_key' => 'test-key',
            'f5ai.api_url' => 'https://api.f5ai.ru/v2',
        ]);

        Http::fake([
            'https://api.f5ai.ru/v2/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => "## Общая оценка\n\nПлан требует доработки.\n\n## Советы\n\n- Добавьте поля UTM"]],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $this->actingAs($user)
            ->postJson(route('projects.ai.analyze', $project))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('analysis', fn ($value) => str_contains($value, 'Общая оценка'));
    }

    public function test_chat_audit_mode_returns_text_without_changes(): void
    {
        config([
            'f5ai.api_key' => 'test-key',
            'f5ai.api_url' => 'https://api.f5ai.ru/v2',
        ]);

        Http::fake([
            'https://api.f5ai.ru/v2/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => "## Аудит\n\nНе хватает полей на контактах и SLA на этапах."]],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $response = $this->actingAs($user)->postJson(route('projects.ai.chat.send', $project), [
            'message' => 'Проведи аудит текущего плана внедрения amoCRM для этого проекта.',
            'mode' => 'audit',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('processing_mode', 'audit');

        $assistant = collect($response->json('messages'))->last();
        $this->assertSame('assistant', $assistant['role']);
        $this->assertNull($assistant['changes']);

        $this->assertDatabaseHas('project_ai_messages', [
            'project_id' => $project->id,
            'role' => ProjectAiMessage::ROLE_ASSISTANT,
        ]);
    }

    public function test_docx_includes_analytical_report_from_ai_chat(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
            'client_tasks' => 'Автоматизация продаж',
        ]);

        ProjectAiMessage::create([
            'project_id' => $project->id,
            'role' => ProjectAiMessage::ROLE_ASSISTANT,
            'content' => "## Кейс внедрения amoCRM\n\nКлиенту нужна воронка B2B и поля для аналитики.\n\n---\n**Настройки проекта подготовлены.**",
            'changes' => ['skk_fields' => ['create' => [['entity_type' => 'deals', 'name' => 'Бюджет']]]],
        ]);

        $report = app(ProjectAiReportExtractor::class)->latestReport($project);
        $this->assertStringContainsString('Кейс внедрения amoCRM', $report ?? '');
        $this->assertStringNotContainsString('Настройки проекта подготовлены', $report ?? '');

        $tempFile = app(ProjectDocxExporter::class)->build($project);
        $this->assertFileExists($tempFile);

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($tempFile));
        $documentXml = $zip->getFromName('word/document.xml');
        $zip->close();
        unlink($tempFile);

        $this->assertStringContainsString('Аналитическая записка', $documentXml);
        $this->assertStringContainsString('Кейс внедрения amoCRM', $documentXml);
    }
}
