<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_profile(): void
    {
        $this->get(route('profile.edit'))
            ->assertRedirect(route('login'));
    }

    public function test_user_sees_profile_page(): void
    {
        $user = User::factory()->create([
            'name' => 'Иван Петров',
            'email' => 'ivan@f5plan.local',
        ]);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Profile/Index')
                ->where('user.name', 'Иван Петров')
                ->where('user.email', 'ivan@f5plan.local')
            );
    }

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Иван',
            'email' => 'ivan@f5plan.local',
        ]);

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => 'Иван Петров',
                'email' => 'petrov@f5plan.local',
            ])
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Иван Петров', $user->name);
        $this->assertSame('petrov@f5plan.local', $user->email);
    }

    public function test_user_can_change_password_with_current_password(): void
    {
        $user = User::factory()->create([
            'password' => 'oldpassword',
        ]);

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'current_password' => 'oldpassword',
                'password' => 'newpassword',
                'password_confirmation' => 'newpassword',
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertTrue(
            auth()->validate([
                'email' => $user->email,
                'password' => 'newpassword',
            ])
        );
    }

    public function test_password_change_requires_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'newpassword',
                'password_confirmation' => 'newpassword',
            ])
            ->assertSessionHasErrors('current_password');
    }
}
