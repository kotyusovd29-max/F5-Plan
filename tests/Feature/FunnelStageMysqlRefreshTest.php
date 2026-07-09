<?php

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\Project;
use App\Models\User;
use Tests\TestCase;

class FunnelStageMysqlRefreshTest extends TestCase
{
    public function test_refresh_existing_funnel_on_mysql(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('MySQL only');
        }

        $user = User::first();
        $project = Project::first();
        $funnel = $project?->funnels()->first();

        if (! $user || ! $project || ! $funnel) {
            $this->markTestSkipped('No seed data');
        }

        $headers = ['X-Canvas-Request' => '1', 'Accept' => 'application/json'];

        $store = $this->actingAs($user)
            ->withHeaders($headers)
            ->post(route('projects.funnels.stages.store', [$project, $funnel]), [
                'name' => 'Mysql Stage '.time(),
                'action' => 'action',
            ]);

        $store->assertOk()->assertJson(['success' => true]);

        $refresh = $this->actingAs($user)
            ->withHeaders($headers)
            ->getJson(route('projects.funnels.canvas.refresh', [$project, $funnel]));

        $refresh->assertOk()->assertJsonStructure(['html', 'title']);
    }
}
