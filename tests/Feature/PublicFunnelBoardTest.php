<?php

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\FunnelBoardComment;
use App\Models\FunnelStage;
use App\Models\Project;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicFunnelBoardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') === 'mysql') {
            $this->markTestSkipped('RefreshDatabase must not run against the development MySQL database.');
        }

        parent::setUp();
    }

    public function test_owner_can_create_and_copy_share_link(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'Client Co',
        ]);
        $funnel = Funnel::create([
            'project_id' => $project->id,
            'name' => 'Sales',
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('projects.funnels.canvas.share', [$project, $funnel]));

        $response->assertOk()
            ->assertJsonStructure(['url']);

        $project->refresh();
        $this->assertNotEmpty($project->board_share_token);
        $this->assertStringContainsString('/b/'.$project->board_share_token, $response->json('url'));
    }

    public function test_guest_can_view_shared_board_readonly(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'Client Co',
        ]);
        $funnel = Funnel::create([
            'project_id' => $project->id,
            'name' => 'Sales',
            'sort_order' => 1,
            'share_token' => 'abcdefghijabcdefghijabcdefghijabcdefghijabcdefghij',
        ]);
        FunnelStage::create([
            'funnel_id' => $funnel->id,
            'name' => 'Новая заявка',
            'action' => 'Позвони клиенту',
            'sort_order' => 1,
            'kind' => 'normal',
            'canvas_pos_x' => 30,
            'canvas_pos_y' => 50,
        ]);

        $this->get(route('funnels.board.public', ['token' => $funnel->share_token]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Funnels/PublicBoard')
                ->where('project.company_name', 'Client Co')
                ->where('funnel.name', 'Sales')
                ->where('funnel.stages.0.name', 'Новая заявка')
                ->where('funnel.stages.0.action', 'Позвони клиенту')
                ->missing('funnel.stages.0.urls')
            );
    }

    public function test_invalid_share_token_returns_404(): void
    {
        $this->get(route('funnels.board.public', ['token' => str_repeat('x', 48)]))
            ->assertNotFound();
    }

    public function test_regenerate_share_token_invalidates_old_link(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'Client Co',
        ]);
        $funnel = Funnel::create([
            'project_id' => $project->id,
            'name' => 'Sales',
            'sort_order' => 1,
            'share_token' => 'oldtokenoldtokenoldtokenoldtokenoldtokenoldtoken12',
        ]);

        $project->forceFill([
            'board_share_token' => 'oldtokenoldtokenoldtokenoldtokenoldtokenoldtoken12',
        ])->save();

        $this->actingAs($user)
            ->postJson(route('projects.funnels.canvas.share', [$project, $funnel]), [
                'regenerate' => true,
            ])
            ->assertOk();

        $project->refresh();
        $funnel->refresh();
        $this->assertNotSame('oldtokenoldtokenoldtokenoldtokenoldtokenoldtoken12', $project->board_share_token);
        $this->assertNull($funnel->share_token);

        $this->get(route('funnels.board.public', ['token' => 'oldtokenoldtokenoldtokenoldtokenoldtokenoldtoken12']))
            ->assertNotFound();
    }

    public function test_guest_can_leave_board_and_stage_comments(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'Client Co',
        ]);
        $funnel = Funnel::create([
            'project_id' => $project->id,
            'name' => 'Sales',
            'sort_order' => 1,
            'share_token' => 'commenttokencommenttokencommenttokencommenttoken12',
        ]);
        $stage = FunnelStage::create([
            'funnel_id' => $funnel->id,
            'name' => 'Новая заявка',
            'sort_order' => 1,
            'kind' => 'normal',
            'canvas_pos_x' => 30,
            'canvas_pos_y' => 50,
        ]);

        $this->post(route('funnels.board.public.comments.store', ['token' => $funnel->share_token]), [
            'author_name' => 'Анна',
            'body' => 'Общий вопрос по схеме',
            'scope' => 'board',
        ])->assertRedirect();

        $this->post(route('funnels.board.public.comments.store', ['token' => $funnel->share_token]), [
            'author_name' => 'Анна',
            'body' => 'Нужен срок на этом этапе',
            'scope' => 'stage',
            'funnel_stage_id' => $stage->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('funnel_board_comments', [
            'funnel_id' => $funnel->id,
            'funnel_stage_id' => null,
            'author_name' => 'Анна',
            'body' => 'Общий вопрос по схеме',
        ]);
        $this->assertDatabaseHas('funnel_board_comments', [
            'funnel_id' => $funnel->id,
            'funnel_stage_id' => $stage->id,
            'body' => 'Нужен срок на этом этапе',
        ]);

        $this->get(route('funnels.board.public', ['token' => $funnel->share_token]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Funnels/PublicBoard')
                ->has('comments', 2)
                ->where('comments.0.author_name', 'Анна')
            );

        $this->actingAs($user)
            ->get(route('projects.funnels.canvas.show', [$project, $funnel]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Funnels/Canvas')
                ->missing('boardComments')
            );

        $boardComment = FunnelBoardComment::query()
            ->where('funnel_id', $funnel->id)
            ->whereNull('funnel_stage_id')
            ->firstOrFail();

        $this->delete(route('funnels.board.public.comments.destroy', [
            'token' => $funnel->share_token,
            'comment' => $boardComment->id,
        ]))->assertRedirect();

        $this->assertDatabaseMissing('funnel_board_comments', [
            'id' => $boardComment->id,
        ]);
    }

    public function test_guest_cannot_delete_comment_from_another_project(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'Client Co',
        ]);
        $otherProject = Project::create([
            'user_id' => $user->id,
            'company_name' => 'Other Co',
        ]);
        $funnel = Funnel::create([
            'project_id' => $project->id,
            'name' => 'Sales',
            'sort_order' => 1,
            'share_token' => 'deletetokendeletetokendeletetokendeletetoken12',
        ]);
        $otherFunnel = Funnel::create([
            'project_id' => $otherProject->id,
            'name' => 'Other',
            'sort_order' => 1,
            'share_token' => 'othertokendeletetokendeletetokendeletetoken99',
        ]);
        $comment = FunnelBoardComment::query()->create([
            'funnel_id' => $otherFunnel->id,
            'funnel_stage_id' => null,
            'author_name' => 'Анна',
            'body' => 'Чужой комментарий',
            'ip_hash' => hash('sha256', '127.0.0.1'),
        ]);

        $this->delete(route('funnels.board.public.comments.destroy', [
            'token' => $funnel->share_token,
            'comment' => $comment->id,
        ]))->assertNotFound();

        $this->assertDatabaseHas('funnel_board_comments', [
            'id' => $comment->id,
        ]);
    }

    public function test_guest_cannot_comment_with_stage_from_another_funnel(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'Client Co',
        ]);
        $funnel = Funnel::create([
            'project_id' => $project->id,
            'name' => 'Sales',
            'sort_order' => 1,
            'share_token' => 'othertokenothertokenothertokenothertokenothertoken1',
        ]);
        $otherFunnel = Funnel::create([
            'project_id' => $project->id,
            'name' => 'Other',
            'sort_order' => 2,
        ]);
        $foreignStage = FunnelStage::create([
            'funnel_id' => $otherFunnel->id,
            'name' => 'Чужой этап',
            'sort_order' => 1,
            'kind' => 'normal',
        ]);

        $this->post(route('funnels.board.public.comments.store', ['token' => $funnel->share_token]), [
            'author_name' => 'Анна',
            'body' => 'Попытка',
            'scope' => 'stage',
            'funnel_stage_id' => $foreignStage->id,
        ])->assertSessionHasErrors('funnel_stage_id');

        $this->assertSame(0, FunnelBoardComment::query()->count());
    }

    public function test_owner_can_configure_share_controls(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'Client Co',
        ]);
        $funnel = Funnel::create([
            'project_id' => $project->id,
            'name' => 'Sales',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->postJson(route('projects.funnels.canvas.share', [$project, $funnel]), [
                'password' => 'secret123',
                'comments_enabled' => false,
                'expires_at' => now()->addDay()->format('Y-m-d\TH:i'),
            ])
            ->assertOk()
            ->assertJsonPath('has_password', true)
            ->assertJsonPath('comments_enabled', false);

        $project->refresh();
        $this->assertTrue($project->hasBoardSharePassword());
        $this->assertFalse($project->boardCommentsEnabled());
        $this->assertNotNull($project->board_share_expires_at);

        $token = $project->board_share_token;

        $this->get(route('funnels.board.public', ['token' => $token]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Funnels/PublicBoardPassword'));

        $this->post(route('funnels.board.public.unlock', ['token' => $token]), [
            'password' => 'wrong',
        ])->assertSessionHasErrors('password');

        $this->post(route('funnels.board.public.unlock', ['token' => $token]), [
            'password' => 'secret123',
        ])->assertRedirect(route('funnels.board.public', ['token' => $token]));

        $this->get(route('funnels.board.public', ['token' => $token]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Funnels/PublicBoard')
                ->where('commentsEnabled', false)
            );

        $this->post(route('funnels.board.public.comments.store', ['token' => $token]), [
            'author_name' => 'Анна',
            'body' => 'Не должно сохраниться',
            'scope' => 'board',
        ])->assertSessionHasErrors('body');

        $this->actingAs($user)
            ->postJson(route('projects.funnels.canvas.share', [$project, $funnel]), [
                'revoke' => true,
            ])
            ->assertOk()
            ->assertJsonPath('url', null);

        $this->assertNull($project->fresh()->board_share_token);
    }

    public function test_expired_share_link_shows_unavailable_page(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'Client Co',
        ]);
        $funnel = Funnel::create([
            'project_id' => $project->id,
            'name' => 'Sales',
            'sort_order' => 1,
            'share_token' => 'expiredtokenexpiredtokenexpiredtokenexpiredtoken12',
            'share_expires_at' => now()->subMinute(),
        ]);

        $this->get(route('funnels.board.public', ['token' => $funnel->share_token]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Funnels/PublicBoardUnavailable')
                ->where('reason', 'expired')
            );
    }

    public function test_board_comment_notifies_owner_and_editors(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $project = Project::create([
            'user_id' => $owner->id,
            'company_name' => 'Client Co',
        ]);
        $project->editors()->attach($editor->id);
        $funnel = Funnel::create([
            'project_id' => $project->id,
            'name' => 'Sales',
            'sort_order' => 1,
            'share_token' => 'notifytokennotifytokennotifytokennotifytokennotif1',
        ]);

        $this->post(route('funnels.board.public.comments.store', ['token' => $funnel->share_token]), [
            'author_name' => 'Клиент',
            'body' => 'Нужно уточнить SLA',
            'scope' => 'board',
        ])->assertRedirect();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $owner->id,
            'type' => UserNotification::TYPE_BOARD_COMMENT,
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $editor->id,
            'type' => UserNotification::TYPE_BOARD_COMMENT,
        ]);
    }

    public function test_public_board_does_not_share_staff_notifications(): void
    {
        $owner = User::factory()->create();
        $project = Project::create([
            'user_id' => $owner->id,
            'company_name' => 'Client Co',
        ]);
        $funnel = Funnel::create([
            'project_id' => $project->id,
            'name' => 'Sales',
            'sort_order' => 1,
            'share_token' => 'publicnotifypublicnotifypublicnotifypublicnotify12',
        ]);

        \App\Models\UserNotification::query()->create([
            'user_id' => $owner->id,
            'type' => UserNotification::TYPE_BOARD_COMMENT,
            'title' => 'Комментарий на доске воронки',
            'body' => 'Тест',
            'url' => route('projects.funnels.canvas.show', [$project, $funnel]),
            'data' => ['comment_id' => 1],
        ]);

        $this->actingAs($owner)
            ->get(route('funnels.board.public', ['token' => $funnel->share_token]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Funnels/PublicBoard')
                ->where('notifications', [])
            );
    }

    public function test_one_share_link_exposes_all_project_funnels(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'Client Co',
            'board_share_token' => 'projectsharetokenprojectsharetokenprojectshare12',
        ]);
        $sales = Funnel::create([
            'project_id' => $project->id,
            'name' => 'Sales',
            'sort_order' => 1,
        ]);
        $support = Funnel::create([
            'project_id' => $project->id,
            'name' => 'Support',
            'sort_order' => 2,
        ]);

        $this->get(route('funnels.board.public', ['token' => $project->board_share_token]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Funnels/PublicBoard')
                ->has('funnels', 2)
                ->where('funnel.id', $sales->id)
                ->where('funnel.name', 'Sales')
            );

        $this->get(route('funnels.board.public', [
            'token' => $project->board_share_token,
            'funnel' => $support->id,
        ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Funnels/PublicBoard')
                ->where('funnel.id', $support->id)
                ->where('funnel.name', 'Support')
            );
    }

    public function test_manager_canvas_receives_funnel_switcher_list(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'user_id' => $user->id,
            'company_name' => 'Client Co',
        ]);
        $sales = Funnel::create([
            'project_id' => $project->id,
            'name' => 'Sales',
            'sort_order' => 1,
        ]);
        Funnel::create([
            'project_id' => $project->id,
            'name' => 'Support',
            'sort_order' => 2,
        ]);

        $this->actingAs($user)
            ->get(route('projects.funnels.canvas.show', [$project, $sales]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Funnels/Canvas')
                ->has('funnels', 2)
                ->where('funnels.0.name', 'Sales')
                ->where('funnels.1.name', 'Support')
            );
    }
}
