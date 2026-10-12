<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectAiMessage;
use App\Models\ProjectBriefDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectBriefDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') === 'mysql') {
            $this->markTestSkipped('RefreshDatabase must not run against the development MySQL database.');
        }

        parent::setUp();
    }

    public function test_authenticated_user_can_upload_txt_brief_document(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'tz-client.txt',
            "Требуется внедрение amoCRM\nВоронка продаж из 5 этапов"
        );

        $response = $this->actingAs($user)->postJson(route('projects.brief-documents.store', $project), [
            'file' => $file,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('document.original_name', 'tz-client.txt');

        $this->assertDatabaseHas('project_brief_documents', [
            'project_id' => $project->id,
            'user_id' => $user->id,
            'original_name' => 'tz-client.txt',
        ]);
    }

    public function test_authenticated_user_can_upload_xlsx_brief_document(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $spreadsheet->getActiveSheet()->setTitle('Смета');
        $spreadsheet->getActiveSheet()->fromArray([
            ['Этап', 'Стоимость'],
            ['Аналитика', 50000],
        ], null, 'A1');

        $tempPath = tempnam(sys_get_temp_dir(), 'brief-xlsx-');
        $xlsxPath = $tempPath.'.xlsx';
        @unlink($tempPath);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($xlsxPath);
        $spreadsheet->disconnectWorksheets();

        $file = new UploadedFile(
            $xlsxPath,
            'estimate.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        try {
            $response = $this->actingAs($user)->postJson(route('projects.brief-documents.store', $project), [
                'file' => $file,
                'kind' => ProjectBriefDocument::KIND_CHAT,
            ]);

            $response->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('document.original_name', 'estimate.xlsx');

            $document = ProjectBriefDocument::query()->where('original_name', 'estimate.xlsx')->first();
            $this->assertNotNull($document);
            $this->assertStringContainsString('Аналитика', (string) $document->extracted_text);
            $this->assertStringContainsString('50000', (string) $document->extracted_text);
        } finally {
            @unlink($xlsxPath);
        }
    }

    public function test_authenticated_user_can_delete_brief_document(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $diskPath = "project-briefs/{$project->id}/brief.txt";
        Storage::disk('local')->put($diskPath, 'ТЗ');

        $document = ProjectBriefDocument::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'original_name' => 'brief.txt',
            'disk_path' => $diskPath,
            'mime_type' => 'text/plain',
            'size' => 4,
            'extracted_text' => 'ТЗ',
            'char_count' => 2,
        ]);

        $this->actingAs($user)
            ->deleteJson(route('projects.brief-documents.destroy', [$project, $document]))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('project_brief_documents', ['id' => $document->id]);
        Storage::disk('local')->assertMissing($diskPath);
    }

    public function test_generate_plan_from_brief_document_queues_tz_plan_response(): void
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
            'original_name' => 'tz.txt',
            'disk_path' => "project-briefs/{$project->id}/tz.txt",
            'mime_type' => 'text/plain',
            'size' => 100,
            'extracted_text' => 'Клиенту нужна воронка B2B продаж и интеграция с мессенджерами.',
            'char_count' => 60,
        ]);

        Http::fake([
            'https://api.f5ai.ru/v2/chat/completions' => Http::sequence()
                ->push([
                    'choices' => [
                        ['message' => ['content' => "## План внедрения\n\n1. Воронка B2B\n2. Chatter"]],
                    ],
                ])
                ->push([
                    'choices' => [
                        ['message' => ['content' => json_encode([
                            'message' => 'Загружу воронку и Chatter по ТЗ.',
                            'changes' => [
                                'funnels' => [
                                    'create' => [
                                        [
                                            'name' => 'B2B продажи',
                                            'description' => 'Основная воронка',
                                            'stages' => [
                                                ['name' => 'Новый лид', 'action' => 'Позвонить', 'comment' => ''],
                                            ],
                                        ],
                                    ],
                                    'update' => [],
                                    'delete' => [],
                                ],
                                'integrations' => [
                                    'create' => [
                                        ['widget_slug' => 'chatter', 'comment' => 'Мессенджеры'],
                                    ],
                                    'update' => [],
                                    'delete' => [],
                                ],
                            ],
                        ], JSON_UNESCAPED_UNICODE)]],
                    ],
                ])
                ->push([
                    'choices' => [
                        ['message' => ['content' => json_encode([
                            'funnels' => [],
                        ], JSON_UNESCAPED_UNICODE)]],
                    ],
                ])
                ->push([
                    'choices' => [
                        ['message' => ['content' => json_encode([
                            'fields' => [],
                        ], JSON_UNESCAPED_UNICODE)]],
                    ],
                ]),
        ]);

        $response = $this->actingAs($user)->postJson(
            route('projects.brief-documents.generate-plan', [$project, $document])
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('processing_mode', 'tz_plan');

        $assistantId = $response->json('messages.1.id');
        $assistant = ProjectAiMessage::find($assistantId);

        $this->assertNotNull($assistant);
        $this->assertSame(ProjectAiMessage::STATUS_COMPLETED, $assistant->status);
        $this->assertStringContainsString('План внедрения', $assistant->content);
        $this->assertTrue($assistant->hasPendingChanges());

        $userMessage = ProjectAiMessage::find($response->json('messages.0.id'));
        $this->assertSame($document->id, $userMessage->brief_document_id);
    }

    public function test_generate_plan_returns_report_when_changes_json_invalid(): void
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
            'original_name' => 'tz.txt',
            'disk_path' => "project-briefs/{$project->id}/tz.txt",
            'mime_type' => 'text/plain',
            'size' => 100,
            'extracted_text' => 'Нужна воронка продаж.',
            'char_count' => 20,
        ]);

        Http::fake([
            'https://api.f5ai.ru/v2/chat/completions' => Http::sequence()
                ->push([
                    'choices' => [
                        ['message' => ['content' => "## План внедрения\n\nВоронка продаж"]],
                    ],
                ])
                ->push([
                    'choices' => [
                        ['message' => ['content' => 'Не JSON, а обычный текст от модели']],
                    ],
                ])
                ->push([
                    'choices' => [
                        ['message' => ['content' => 'Снова невалидный ответ']],
                    ],
                ])
                ->push([
                    'choices' => [
                        ['message' => ['content' => 'И снова не JSON']],
                    ],
                ])
                ->push([
                    'choices' => [
                        ['message' => ['content' => 'И ещё раз не JSON']],
                    ],
                ])
                ->push([
                    'choices' => [
                        ['message' => ['content' => '{"funnels":[]}']],
                    ],
                ])
                ->push([
                    'choices' => [
                        ['message' => ['content' => '{"fields":[]}']],
                    ],
                ]),
        ]);

        $response = $this->actingAs($user)->postJson(
            route('projects.brief-documents.generate-plan', [$project, $document])
        );

        $response->assertOk()->assertJsonPath('success', true);

        $assistant = ProjectAiMessage::find($response->json('messages.1.id'));

        $this->assertNotNull($assistant);
        $this->assertSame(ProjectAiMessage::STATUS_COMPLETED, $assistant->status);
        $this->assertStringContainsString('План по ТЗ готов', $assistant->content);
        $this->assertStringContainsString('блок изменений не собрался', $assistant->content);
        $this->assertFalse($assistant->hasPendingChanges());
    }

    public function test_project_show_includes_brief_documents(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        ProjectBriefDocument::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'original_name' => 'tz.docx',
            'disk_path' => "project-briefs/{$project->id}/tz.docx",
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'size' => 2048,
            'extracted_text' => 'ТЗ клиента',
            'char_count' => 11,
        ]);

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Projects/Show')
                ->has('briefDocuments', 1)
                ->where('briefDocuments.0.original_name', 'tz.docx')
            );
    }

    public function test_authenticated_user_can_upload_image_for_ai_chat(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
        ]);

        $file = UploadedFile::fake()->image('board-screenshot.png', 640, 480);

        $response = $this->actingAs($user)->postJson(route('projects.brief-documents.store', $project), [
            'file' => $file,
            'kind' => ProjectBriefDocument::KIND_CHAT,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('document.original_name', 'board-screenshot.png')
            ->assertJsonPath('document.is_image', true);

        $document = ProjectBriefDocument::query()->where('original_name', 'board-screenshot.png')->first();
        $this->assertNotNull($document);
        $this->assertTrue($document->isImage());
        $this->assertNull($document->extracted_text);
        $this->assertNotNull($document->toDataUrl());
        $this->assertStringStartsWith('data:image/', $document->toDataUrl());
    }
}
