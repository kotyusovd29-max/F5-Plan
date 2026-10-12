<?php

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FunnelStageCanvasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') === 'mysql') {
            $this->markTestSkipped('RefreshDatabase must not run against the development MySQL database.');
        }

        parent::setUp();
    }

    public function test_canvas_stage_store_and_refresh(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'Test Company',
        ]);
        $funnel = Funnel::create([
            'project_id' => $project->id,
            'name' => 'Sales',
            'sort_order' => 1,
        ]);

        $headers = [
            'X-Canvas-Request' => '1',
            'Accept' => 'application/json',
        ];

        $store = $this->actingAs($user)
            ->withHeaders($headers)
            ->post(route('projects.funnels.stages.store', [$project, $funnel]), [
                'name' => 'Stage 1',
                'action' => 'Do something',
                'comment' => 'Note',
            ]);

        $store->assertOk()->assertJson(['success' => true]);

        $refresh = $this->actingAs($user)
            ->withHeaders($headers)
            ->getJson(route('projects.funnels.canvas.refresh', [$project, $funnel]));

        $refresh->assertOk()->assertJsonStructure(['stages', 'title']);
    }
}
