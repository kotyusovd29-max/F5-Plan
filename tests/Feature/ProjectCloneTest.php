<?php

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\Project;
use App\Models\ProjectIntegration;
use App\Models\ProjectSkkField;
use App\Models\ProjectWorkStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectCloneTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_clone_project_structure(): void
    {
        $user = User::factory()->create();
        $source = Project::query()->create([
            'user_id' => $user->id,
            'company_name' => 'ООО Альфа',
            'business_sphere' => 'Логистика',
            'website' => 'https://alpha.example',
            'client_tasks' => 'Секрет клиента',
            'amocrm_deal_url' => 'https://example.amocrm.ru/leads/detail/1',
            'status' => Project::STATUS_IN_IMPLEMENTATION,
        ]);

        $funnel = $source->funnels()->create([
            'name' => 'Продажи',
            'sort_order' => 1,
            'amocrm_id' => 111,
        ]);
        $stage = $funnel->stages()->create([
            'name' => 'Новый',
            'action' => 'Позвонить',
            'sort_order' => 1,
            'color' => '#99ccff',
            'amocrm_id' => 222,
        ]);

        $source->skkFields()->create([
            'entity_type' => ProjectSkkField::TYPE_DEALS,
            'name' => 'Источник',
            'value_type' => ProjectSkkField::VALUE_SELECT,
            'options' => ['Сайт', 'Реклама'],
            'funnel_stage_id' => $stage->id,
            'sort_order' => 1,
            'amocrm_id' => 333,
            'show_in_card' => true,
        ]);

        $source->integrations()->create([
            'name' => 'Chatter',
            'widget_slug' => 'chatter',
            'widget_url' => 'https://cmdf5.ru/chatter',
            'comment' => 'Мессенджеры',
            'billing_quantity' => 2,
            'sort_order' => 1,
        ]);

        $source->workStages()->create([
            'name' => 'Настройка',
            'composition' => 'Базовая настройка',
            'cost' => 50000,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($user)
            ->post(route('projects.clone', $source))
            ->assertRedirect();

        $clone = Project::query()
            ->where('user_id', $user->id)
            ->where('id', '!=', $source->id)
            ->first();

        $this->assertNotNull($clone);
        $response->assertRedirect(route('projects.show', $clone));

        $this->assertSame('Копия: ООО Альфа', $clone->company_name);
        $this->assertSame('Логистика', $clone->business_sphere);
        $this->assertNull($clone->website);
        $this->assertNull($clone->client_tasks);
        $this->assertNull($clone->amocrm_deal_url);
        $this->assertSame(Project::STATUS_DRAFT, $clone->status);

        $clonedFunnel = Funnel::query()->where('project_id', $clone->id)->first();
        $this->assertNotNull($clonedFunnel);
        $this->assertSame('Продажи', $clonedFunnel->name);
        $this->assertNull($clonedFunnel->amocrm_id);
        $this->assertCount(1, $clonedFunnel->stages);
        $this->assertNull($clonedFunnel->stages->first()->amocrm_id);

        $clonedField = ProjectSkkField::query()->where('project_id', $clone->id)->first();
        $this->assertNotNull($clonedField);
        $this->assertSame('Источник', $clonedField->name);
        $this->assertSame($clonedFunnel->stages->first()->id, $clonedField->funnel_stage_id);
        $this->assertNull($clonedField->amocrm_id);

        $this->assertDatabaseHas('project_integrations', [
            'project_id' => $clone->id,
            'widget_slug' => 'chatter',
            'billing_quantity' => 2,
        ]);

        $this->assertDatabaseHas('project_work_stages', [
            'project_id' => $clone->id,
            'name' => 'Настройка',
            'cost' => 50000,
        ]);

        $this->assertSame(1, Funnel::query()->where('project_id', $source->id)->count());
        $this->assertSame(1, ProjectIntegration::query()->where('project_id', $source->id)->count());
        $this->assertSame(1, ProjectWorkStage::query()->where('project_id', $source->id)->count());
    }

    public function test_editor_can_clone_shared_project_as_own(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();

        $source = Project::query()->create([
            'user_id' => $owner->id,
            'company_name' => 'Шаблон B2B',
        ]);
        $source->editors()->attach($editor->id);

        $source->funnels()->create([
            'name' => 'Основная',
            'sort_order' => 1,
        ]);

        $this->actingAs($editor)
            ->post(route('projects.clone', $source))
            ->assertRedirect();

        $clone = Project::query()
            ->where('user_id', $editor->id)
            ->where('company_name', 'Копия: Шаблон B2B')
            ->first();

        $this->assertNotNull($clone);
        $this->assertSame(1, $clone->funnels()->count());
    }
}
