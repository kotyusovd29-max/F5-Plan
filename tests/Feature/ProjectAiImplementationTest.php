<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Services\ProjectAiChangesApplier;
use App\Services\ProjectAiImplementationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectAiImplementationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') === 'mysql') {
            $this->markTestSkipped('RefreshDatabase must not run against the development MySQL database.');
        }

        parent::setUp();
    }

    public function test_detects_large_implementation_brief(): void
    {
        $service = app(ProjectAiImplementationService::class);

        $this->assertTrue($service->isImplementationBrief(str_repeat('а', 1600)));
        $this->assertTrue($service->isImplementationBrief('Подготовь кейс внедрения amoCRM для производства'));
    }

    public function test_applies_funnel_with_nested_stages(): void
    {
        $project = Project::create([
            'user_id' => User::factory()->create()->id,
            'company_name' => 'Бастион',
        ]);

        $counts = app(ProjectAiChangesApplier::class)->apply($project, [
            'funnels' => [
                'create' => [
                    [
                        'name' => 'Продажи',
                        'description' => 'Основная воронка',
                        'stages' => [
                            ['name' => 'Новый лид', 'action' => 'Позвонить ≤ 15 мин', 'comment' => 'SLA'],
                            ['name' => 'Квалификация', 'action' => 'BANT-скрипт', 'comment' => ''],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame(3, $counts['created']);

        $funnel = $project->fresh()->funnels()->first();
        $this->assertNotNull($funnel);
        $this->assertSame('Продажи', $funnel->name);
        $this->assertSame(2, $funnel->stages()->count());
    }
}
