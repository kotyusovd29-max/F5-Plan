<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserPresenceSession;
use App\Services\UserPresenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPresenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_heartbeat(): void
    {
        $this->postJson(route('presence.heartbeat'))
            ->assertUnauthorized();
    }

    public function test_heartbeat_starts_session_and_accumulates_time(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson(route('presence.heartbeat'))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('user_presence_sessions', [
            'user_id' => $user->id,
            'duration_seconds' => 0,
            'ended_at' => null,
        ]);

        $session = UserPresenceSession::query()->firstOrFail();
        $session->forceFill([
            'last_seen_at' => now()->subSeconds(30),
        ])->save();

        $this->postJson(route('presence.heartbeat'))
            ->assertOk();

        $user->refresh();
        $session->refresh();

        $this->assertGreaterThanOrEqual(30, $user->online_seconds_total);
        $this->assertGreaterThanOrEqual(30, $user->online_seconds_today);
        $this->assertGreaterThanOrEqual(30, $session->duration_seconds);
        $this->assertNotNull($user->last_seen_at);
    }

    public function test_admin_users_index_groups_employees_under_rop(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Админ А',
            'online_seconds_total' => 120,
            'online_seconds_today' => 60,
            'online_day' => now()->toDateString(),
        ]);
        $rop = User::factory()->rop()->create(['name' => 'РОП Белов']);
        $employee = User::factory()->employee($rop)->create(['name' => 'Сотрудник Сидоров']);
        User::factory()->employee()->create(['name' => 'Без РОПа', 'rop_id' => null]);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Users/Index')
                ->has('groups')
                ->where('groups.0.kind', 'admins')
                ->where('groups.0.users.0.id', $admin->id)
                ->where('groups.1.kind', 'rop')
                ->where('groups.1.title', 'Отдел: РОП Белов')
                ->where('groups.1.users.0.id', $rop->id)
                ->where('groups.1.users.1.id', $employee->id)
                ->where('groups.2.kind', 'orphans')
                ->where('groups.0.users.0.online_total_label', '2 мин')
            );
    }

    public function test_presence_service_marks_online(): void
    {
        $user = User::factory()->create([
            'last_seen_at' => now()->subSeconds(30),
        ]);

        $this->assertTrue(app(UserPresenceService::class)->isOnline($user));
    }
}
