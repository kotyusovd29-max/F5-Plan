<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProjectKpPptxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') === 'mysql') {
            $this->markTestSkipped('RefreshDatabase must not run against the development MySQL database.');
        }

        parent::setUp();
    }

    public function test_user_can_download_kp_pptx(): void
    {
        config([
            'f5ai.api_key' => 'test-key',
            'f5ai.api_url' => 'https://api.f5ai.test/v2',
            'f5ai.model' => 'gpt-test',
        ]);

        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'company_name' => 'ООО КП Тест',
            'business_sphere' => 'Ритейл',
        ]);
        $project->workStages()->create([
            'name' => 'Аналитика',
            'composition' => 'Аудит процессов',
            'cost' => 50000,
            'duration_days' => 5,
        ]);

        Http::fake([
            'https://api.f5ai.test/v2/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'slides' => [
                                ['layout' => 'title', 'title' => 'Внедрение amoCRM', 'subtitle' => 'для ООО КП Тест'],
                                ['layout' => 'bullets', 'title' => 'О клиенте', 'bullets' => ['Сфера: Ритейл']],
                                ['layout' => 'table', 'title' => 'Этапы', 'headers' => ['Этап', 'Стоимость'], 'rows' => [['Аналитика', '50 000 ₽']]],
                            ],
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ]],
            ]),
        ]);

        $response = $this->actingAs($user)->get(route('projects.export.kp-pptx', $project));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.presentationml.presentation');
        $this->assertStringContainsString('.pptx', (string) $response->headers->get('content-disposition'));
    }

    public function test_kp_pptx_requires_slides_from_ai(): void
    {
        config([
            'f5ai.api_key' => 'test-key',
            'f5ai.api_url' => 'https://api.f5ai.test/v2',
        ]);

        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'company_name' => 'ООО Пусто',
        ]);

        Http::fake([
            'https://api.f5ai.test/v2/chat/completions' => Http::response([
                'choices' => [[
                    'message' => ['content' => 'не json'],
                ]],
            ]),
        ]);

        $this->actingAs($user)
            ->get(route('projects.export.kp-pptx', $project))
            ->assertStatus(500);
    }
}
