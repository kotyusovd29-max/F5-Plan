<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_for_guests(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/Login')
            );
    }

    public function test_home_redirects_guests_to_login(): void
    {
        $this->get(route('home'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_sees_home_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Home/Index')
                ->where('view', 'mine')
            );
    }

    public function test_user_can_log_in(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
        ]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
    }
}
