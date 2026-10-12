<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectBriefDocument;
use App\Models\ProjectWorkStage;
use App\Models\User;
use App\Services\ProjectAiChangesApplier;
use App\Services\ProjectAiChangesParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectKpWorkStagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') === 'mysql') {
            $this->markTestSkipped('RefreshDatabase must not run against the development MySQL database.');
        }

        parent::setUp();
    }

    public function test_parser_normalizes_work_stages(): void
    {
        $parser = app(ProjectAiChangesParser::class);

        $parsed = $parser->parse(json_encode([
            'message' => 'Нашёл 2 этапа',
            'changes' => [
                'work_stages' => [
                    'delete' => [3],
                    'create' => [
                        ['name' => 'Аналитика', 'composition' => 'Обследование', 'cost' => '50 000 ₽', 'duration_days' => '10 раб. дней'],
                        ['name' => 'Настройка', 'composition' => '', 'cost' => null, 'duration_days' => null],
                    ],
                    'update' => [
                        ['id' => 1, 'name' => 'Внедрение', 'cost' => 120000.5, 'duration_days' => 15],
                    ],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE));

        $this->assertSame('Нашёл 2 этапа', $parsed['message']);
        $this->assertSame([3], $parsed['changes']['work_stages']['delete']);
        $this->assertSame('Аналитика', $parsed['changes']['work_stages']['create'][0]['name']);
        $this->assertSame("1) Обследование", $parsed['changes']['work_stages']['create'][0]['composition']);
        $this->assertSame(50000.0, $parsed['changes']['work_stages']['create'][0]['cost']);
        $this->assertSame(10, $parsed['changes']['work_stages']['create'][0]['duration_days']);
        $this->assertNull($parsed['changes']['work_stages']['create'][1]['composition']);
        $this->assertNull($parsed['changes']['work_stages']['create'][1]['duration_days']);
        $this->assertSame(120000.5, $parsed['changes']['work_stages']['update'][0]['cost']);
        $this->assertSame(15, $parsed['changes']['work_stages']['update'][0]['duration_days']);
    }

    public function test_applier_can_replace_work_stages(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $old = $project->workStages()->create([
            'name' => 'Старый',
            'composition' => 'Устарело',
            'cost' => 1000,
            'duration_days' => 3,
            'sort_order' => 1,
        ]);

        $counts = app(ProjectAiChangesApplier::class)->apply($project, [
            'work_stages' => [
                'delete' => [$old->id],
                'create' => [
                    ['name' => 'Аудит', 'composition' => 'Интервью', 'cost' => 45000, 'duration_days' => 5],
                    ['name' => 'Настройка', 'composition' => 'CRM', 'cost' => 70000, 'duration_days' => 12],
                ],
                'update' => [],
            ],
        ]);

        $this->assertSame(2, $counts['created']);
        $this->assertSame(1, $counts['deleted']);
        $this->assertFalse(ProjectWorkStage::query()->whereKey($old->id)->exists());
        $this->assertDatabaseHas('project_work_stages', [
            'project_id' => $project->id,
            'name' => 'Аудит',
            'cost' => 45000,
            'duration_days' => 5,
        ]);
        $this->assertDatabaseHas('project_work_stages', [
            'project_id' => $project->id,
            'name' => 'Настройка',
            'duration_days' => 12,
        ]);
    }

    public function test_user_can_upload_kp_document_with_kind(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'kp.txt',
            "Этапы работ\n1. Аналитика — 40 000\n2. Внедрение — 80 000\n"
        );

        $this->actingAs($user)
            ->post(route('projects.brief-documents.store', $project), [
                'file' => $file,
                'kind' => 'kp',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('document.kind', 'kp');

        $document = ProjectBriefDocument::query()->where('project_id', $project->id)->first();
        $this->assertNotNull($document);
        $this->assertSame(ProjectBriefDocument::KIND_KP, $document->kind);
        $this->assertStringContainsString('Аналитика', (string) $document->extracted_text);
        $this->assertTrue(
            route('projects.brief-documents.generate-work-stages', [$project, $document]) !== ''
        );
    }
}
