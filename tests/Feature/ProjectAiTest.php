<?php

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\Project;
use App\Models\ProjectIntegration;
use App\Models\ProjectSkkField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProjectAiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') === 'mysql') {
            $this->markTestSkipped('RefreshDatabase must not run against the development MySQL database.');
        }

        parent::setUp();
    }

    public function test_generate_requires_authentication(): void
    {
        $project = Project::create([
            'user_id' => User::factory()->create()->id,
            'company_name' => 'Test Company',
        ]);

        $this->postJson(route('projects.ai.generate', $project), [
            'sections' => ['skk_fields'],
        ])->assertUnauthorized();
    }

    public function test_generate_and_apply_with_mocked_f5ai_response(): void
    {
        config([
            'f5ai.api_key' => 'test-key',
            'f5ai.api_url' => 'https://api.f5ai.ru/v2',
            'f5ai.model' => 'gpt-5.4',
        ]);

        Http::fake([
            'https://api.f5ai.ru/v2/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'skk_fields' => [
                                    'deals' => [
                                        ['name' => 'Бюджет', 'comment' => 'Сумма сделки'],
                                    ],
                                    'contacts' => [],
                                    'companies' => [],
                                ],
                                'integrations' => [
                                    ['name' => 'WhatsApp', 'comment' => 'Переписка с клиентами'],
                                ],
                                'funnels' => [
                                    [
                                        'name' => 'Продажи',
                                        'description' => 'Основная воронка',
                                        'stages' => [
                                            ['name' => 'Новый лид', 'action' => 'Позвонить', 'comment' => ''],
                                            ['name' => 'КП отправлено', 'action' => 'Ждать ответ', 'comment' => ''],
                                        ],
                                    ],
                                ],
                            ], JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'ООО Тест',
            'employees_count' => 25,
            'client_tasks' => 'Автоматизировать продажи',
        ]);

        $generate = $this->actingAs($user)->postJson(route('projects.ai.generate', $project), [
            'company_name' => 'ООО Тест',
            'employees_count' => 25,
            'client_tasks' => 'Автоматизировать продажи',
            'sections' => ['skk_fields', 'integrations', 'funnels'],
        ]);

        $generate->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.skk_fields', 1)
            ->assertJsonPath('summary.integrations', 1)
            ->assertJsonPath('summary.funnels', 1)
            ->assertJsonPath('summary.stages', 2);

        $proposal = $generate->json('proposal');

        $apply = $this->actingAs($user)->postJson(route('projects.ai.apply', $project), [
            'sections' => ['skk_fields', 'integrations', 'funnels'],
            'proposal' => $proposal,
        ]);

        $apply->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('counts.skk_fields', 1)
            ->assertJsonPath('counts.integrations', 1)
            ->assertJsonPath('counts.funnels', 1)
            ->assertJsonPath('counts.stages', 2);

        $this->assertDatabaseHas('project_skk_fields', [
            'project_id' => $project->id,
            'entity_type' => 'deals',
            'name' => 'Бюджет',
        ]);

        $this->assertDatabaseHas('project_integrations', [
            'project_id' => $project->id,
            'name' => 'WhatsApp',
        ]);

        $funnel = Funnel::query()->where('project_id', $project->id)->first();
        $this->assertNotNull($funnel);
        $this->assertSame('Продажи', $funnel->name);
        $this->assertSame(2, $funnel->stages()->count());
    }

    public function test_generate_fails_without_api_key(): void
    {
        config(['f5ai.api_key' => null]);

        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'Test Company',
        ]);

        $this->actingAs($user)
            ->postJson(route('projects.ai.generate', $project), [
                'sections' => ['skk_fields'],
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
